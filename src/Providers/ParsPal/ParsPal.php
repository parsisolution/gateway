<?php

namespace Parsisolution\Gateway\Providers\ParsPal;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class ParsPal extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://api.parspal.com/v1/payment/';

    /**
     * Address of sandbox server
     *
     * @var string
     */
    const SERVER_SANDBOX_URL = 'https://sandbox.api.parspal.com/v1/payment/';

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
        return GatewayManager::PARSPAL;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'amount'      => $transaction->getAmount()->getTotal(),
            'currency'    => $transaction->getAmount()->getCurrency(),
            'return_url'  => $this->getCallback($transaction),
            'reserve_id'  => $transaction->getOrderId(),
            'order_id'    => $transaction->getOrderId(),
            'payer'       => [
                'name'   => $transaction->getExtraField('name'),
                'mobile' => $transaction->getExtraField('mobile'),
                'email'  => $transaction->getExtraField('email'),
            ],
            'description' => $transaction->getExtraField('description'),
            'default_psp' => $transaction->getExtraField('default_psp'),
        ];

        $result = $this->callApi('request', $fields);

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $result['link']);

        return AuthorizedTransaction::make($transaction, $result['payment_id'], null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (!$request->has('status')) {
            throw new InvalidRequestException();
        }

        $status = $request->input('status');
        if ($status != 100) {
            throw new ParsPalException($status);
        }

//        $reserveId = $request->input('reserve_id');

        return new FieldsToMatch($request->input('order_id'), $request->input('payment_id'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'amount'         => $transaction->getAmount()->getTotal(),
            'currency'       => $transaction->getAmount()->getCurrency(),
            'receipt_number' => $request->input('receipt_number'),
        ];

        $result = $this->callApi('verify', $fields);

        $amount = $result['paid_amount'];
        $currency = $result['currency'];
        $id = $result['id'];

        $toMatch = new FieldsToMatch(null, $id, null, new Amount($amount, $currency));

        return new SettledTransaction(
            $transaction,
            $id,
            $toMatch,
            '',
            '',
            ['verify_result' => $result]
        );
    }

    /**
     * @param string $paymentId
     * @param int $amount
     * @return array
     * @throws ParsPalException
     */
    public function inquiry(string $paymentId, int $amount): array
    {
        return $this->callApi('inquiry', [
            'payment_id' => $paymentId,
            'amount'     => $amount
        ]);
    }

    /**
     * @param string $path
     * @param array $fields
     * @return mixed
     * @throws ParsPalException
     */
    protected function callApi(string $path, array $fields)
    {
        list($response, $http_code, $error) = Curl::execute($this->serverUrl . $path, $fields, true, [
            CURLOPT_HTTPHEADER => $this->generateHeaders(),
        ]);

        if (
            $http_code != 200 ||
            empty($response['status']) ||
            !in_array($response['status'], ['ACCEPTED', 'SUCCESSFUL', 'VERIFIED'])
        ) {
            throw new ParsPalException(
                $response['error_code'] ?? $response['error_type'] ?? $http_code,
                $response['message'] ?? $error ?? null
            );
        }

        return $response;
    }

    /**
     * @return string[]
     */
    protected function generateHeaders(): array
    {
        return [
            'Accept: application/json',
            'Content-Type: application/json',
            'ApiKey: ' . $this->config['api-key'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'      => '09124441122',
            'name'        => 'نام پرداخت کننده',
            'email'       => 'test@gmail.com',
            'description' => 'توضیحات پرداخت',
            'default_psp' => 'شرکت پرداخت پیش فرض - در صورت تعیین کاربر به صورت مستقیم به صفحه پرداخت PSP منتقل خواهد شد',
        ];
    }
}
