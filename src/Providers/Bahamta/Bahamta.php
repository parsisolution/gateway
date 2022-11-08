<?php

namespace Parsisolution\Gateway\Providers\Bahamta;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Bahamta extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://webpay.bahamta.com/api/';

    /**
     * Address of sandbox server
     *
     * @var string
     */
    const SERVER_SANDBOX_URL = 'https://testwebpay.bahamta.com/api/';

    /**
     * Address of main server
     *
     * @var string
     */
    protected $serverUrl;

    public function __construct(Container $app, array $config)
    {
        parent::__construct($app, $config);

        $this->setServer();
    }

    /**
     * Set server for soap transfers data
     *
     * @return void
     */
    protected function setServer()
    {
        if (Arr::get($this->config, 'sandbox', false)) {
            $this->serverUrl = self::SERVER_SANDBOX_URL;
        } else {
            $this->serverUrl = self::SERVER_URL;
        }
    }


    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::BAHAMTA;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'api_key'      => $this->config['api-key'],
            'reference'    => $transaction->getOrderId(),
            'amount_irr'   => $transaction->getAmount()->getRiyal(),
            'callback_url' => $this->getCallback($transaction),
            'trusted_pan'  => $transaction->getExtraField('allowed_card'),
        ];
        $mobile = $transaction->getExtraField('mobile');
        if (!empty($mobile)) {
            $fields['payer_mobile'] = '98' . substr($mobile, 1);
        }

        $result = $this->callApi('create_request', $fields);

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $result['payment_url']);

        return AuthorizedTransaction::make($transaction, null, null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (!$request->has('state')) {
            throw new InvalidRequestException();
        }

        $state = $request->input('state');
        if ($state == 'error') {
            throw new BahamtaException($request->input('error_key', $state), $request->input('error_message'));
        }

        return new FieldsToMatch($request->input('reference'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'api_key'    => $this->config['api-key'],
            'reference'  => $transaction->getOrderId(),
            'amount_irr' => $transaction->getAmount()->getRiyal(),
        ];

        $result = $this->callApi('confirm_payment', $fields);

        if ($result['state'] != 'paid') {
            throw new BahamtaException($result['state']);
        };

        $traceNumber = $result['pay_trace'];
        $cardNumber = $result['pay_pan'];

        $toMatch = new FieldsToMatch();

        return new SettledTransaction(
            $transaction,
            $traceNumber,
            $toMatch,
            $cardNumber,
            '',
            [
                'verify_result' => $result,
                'hashed_card'   => $result['pay_cid'],
                'pay_time'      => $result['pay_time'],
                'pay_reference' => $result['pay_ref'],
            ],
            $result['pay_ref']
        );
    }

    /**
     * @param $path
     * @param $fields
     * @return mixed
     * @throws BahamtaException
     */
    protected function callApi($path, $fields)
    {
        list($response, $http_code, $error) =
            Curl::execute($this->serverUrl . $path, $fields, true, [], Curl::METHOD_GET);

        if (!isset($response['ok'])) {
            throw new BahamtaException($http_code, $error);
        }
        if (!$response['ok']) {
            throw new BahamtaException($response['error'] ?? 'UNKNOWN_ERROR');
        }

        return $response['result'];
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'       => '09124441122',
            'description'  => 'توضیحات تراکنش',
            'allowed_card' => 'شماره کارت پرداخت‌کننده است' .
                ' که این شماره کارت بعد از انجام عملیات پرداخت با شماره کارت دریافتی از بانک تطابق داده می‌شود' .
                ' و درصورتی که یکسان نباشد، مبلغ تراکنش به حساب پرداخت‌کننده برمی‌گردد.',
        ];
    }
}
