<?php

namespace Parsisolution\Gateway\Providers\BitPay;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class BitPay extends AbstractProvider implements ProviderInterface
{
    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://bitpay.ir/payment/';

    /**
     * Address of sandbox server
     *
     * @var string
     */
    const SERVER_SANDBOX_URL = 'https://bitpay.ir/payment-test/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://bitpay.ir/payment/gateway-';

    /**
     * Address of sandbox gate for redirect
     *
     * @var string
     */
    const GATE_SANDBOX_URL = 'https://bitpay.ir/payment-test/gateway-';

    /**
     * Address of main server
     *
     * @var string
     */
    protected $serverUrl;

    /**
     * Address of main gate for redirect
     *
     * @var string
     */
    protected $gateUrl;

    public function __construct(Container $app, $id, $config)
    {
        parent::__construct($app, $id, $config);

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
            $this->gateUrl = self::GATE_SANDBOX_URL;
        } else {
            $this->serverUrl = self::SERVER_URL;
            $this->gateUrl = self::GATE_URL;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'api'         => $this->config['api-key'],
            'amount'      => $transaction->getAmount()->getRiyal(),
            'redirect'    => $this->getCallback($transaction),
            'factorId'    => $transaction->getOrderId(),
            'name'        => $transaction->getExtraField('name'),
            'email'       => $transaction->getExtraField('email'),
            'description' => $transaction->getExtraField('description'),
        ];

        $id = $this->callApi('gateway-send', $fields);

        if (! is_numeric($id) || $id <= 0) {
            throw new BitPayException($id);
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $this->gateUrl.$id.'-get');

        return AuthorizedTransaction::make($transaction, $id, null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('id_get')) {
            throw new InvalidRequestException();
        }

        $id = $request->input('id_get');
        if ($id <= 0) {
            throw new BitPayException($id);
        }

        return new FieldsToMatch(null, $id);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $transId = $request->input('trans_id');

        $fields = [
            'api'      => $this->config['api-key'],
            'trans_id' => $transId,
            'id_get'   => $transaction->getReferenceId(),
            'json'     => 1,
        ];

        $result = $this->callApi('gateway-result-second', $fields);

        if (empty($result['status']) || $result['status'] <= 0) {
            throw new BitPayException($result['status'] ?? 0, $this->getStatusMessage($result['status']));
        }

        $amount = $result['amount'];
        $cardNumber = $result['cardNum'];

        $toMatch = new FieldsToMatch($result['factorId'], null, null, new Amount($amount, 'IRR'));

        return new SettledTransaction(
            $transaction,
            $transId,
            $toMatch,
            $cardNumber,
            '',
            ['verify_result' => $result]
        );
    }

    /**
     * @return mixed
     *
     * @throws BitPayException
     */
    protected function callApi(string $path, array $fields)
    {
        [$response, $http_code, $error] = Curl::execute($this->serverUrl.$path, $fields, true, [
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_POSTFIELDS => $fields,
        ]);

        if ($http_code != 200) {
            throw new BitPayException($response ?? $http_code, $response ?? $error ?? null);
        }

        return $response;
    }

    protected function getStatusMessage(int $code): ?string
    {
        $status_codes = [
            -2 => 'trans_id ارسال شده، داده عددي نمی‌باشد',
            -3 => 'id_get ارسال شده، داده عددي نمی‌باشد',
            -4 => 'چنین تراکنشی در پایگاه وجود ندارد و یا موفقیت آمیز نبوده است',
        ];

        return $status_codes[$code] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'name'        => 'نام پرداخت کننده',
            'email'       => 'test@gmail.com',
            'description' => 'توضیحات پرداخت',
        ];
    }
}
