<?php

namespace Parsisolution\Gateway\Providers\IranDargah;

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

class IranDargah extends AbstractProvider implements ProviderInterface
{
    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://dargaah.com';

    /**
     * Address of sandbox server
     *
     * @var string
     */
    const SERVER_SANDBOX_URL = 'https://dargaah.com/sandbox';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://dargaah.com/ird/startpay/';

    /**
     * Address of sandbox gate for redirect
     *
     * @var string
     */
    const GATE_SANDBOX_URL = 'https://dargaah.com/sandbox/ird/startpay/';

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

    /**
     * Merchant Id
     *
     * @var string
     */
    protected $merchantId;

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
            $this->merchantId = 'TEST';
        } else {
            $this->serverUrl = self::SERVER_URL;
            $this->gateUrl = self::GATE_URL;
            $this->merchantId = Arr::get($this->config, 'merchant-id');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'merchantID'  => $this->merchantId,
            'amount'      => $transaction->getAmount()->getRiyal(),
            'callbackURL' => $this->getCallback($transaction),
            'orderId'     => $transaction->getOrderId(),
            'mobile'      => $transaction->getExtraField('mobile'),
            'description' => $transaction->getExtraField('description'),
        ];
        $cardNumber = $transaction->getExtraField('allowed_card');
        if (! empty($cardNumber)) {
            // by sending cardnumber , your user can not pay with another card number // OPTIONAL
            $fields['cardNumber'] = $cardNumber;
        }

        [$result, $http_code] = Curl::execute($this->serverUrl.'/payment', $fields, false, [
            // if you get SSL error (curl error 60) add 2 lines below
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            // end SSL error
        ]);

        if (! isset($result->status) || $result->status != '200') {
            throw new IranDargahException($result->status ?? $http_code, $result->message);
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $this->gateUrl.$result->authority);

        return AuthorizedTransaction::make($transaction, $result->authority, null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('code')) {
            throw new InvalidRequestException();
        }

        $code = $request->input('code');
        if ($code != 100) {
            throw new IranDargahException($code/*, $request->input('message')*/);
        }

        $orderId = $request->input('orderId');
        $referenceId = $request->input('authority');
        $amount = $request->input('amount');

        return new FieldsToMatch($orderId, $referenceId, null, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'merchantID' => $this->merchantId,
            'authority'  => $transaction->getReferenceId(),
            'amount'     => $transaction->getAmount()->getRiyal(),
            'orderId'    => $transaction->getOrderId(),
        ];

        [$result] = Curl::execute($this->serverUrl.'/verification', $fields, false, [
            // if you get SSL error (curl error 60) add 2 lines below
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            // end SSL error
        ]);

        $status = $result->status;
        if ($status != 100) {
            throw new IranDargahException($status/*, $result->message*/);
        }

        $toMatch = new FieldsToMatch($result->orderId);

        return new SettledTransaction($transaction, $result->refId, $toMatch, $result->cardNumber);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'       => '09124441122',
            'description'  => 'توضیحات تراکنش',
            'allowed_card' => 'شماره کارت پرداخت‌کننده است'.
                ' که این شماره کارت بعد از انجام عملیات پرداخت با شماره کارت دریافتی از بانک تطابق داده می‌شود'.
                ' و درصورتی که یکسان نباشد، مبلغ تراکنش به حساب پرداخت‌کننده برمی‌گردد.',
        ];
    }
}
