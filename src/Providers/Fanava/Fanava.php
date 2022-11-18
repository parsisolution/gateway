<?php

namespace Parsisolution\Gateway\Providers\Fanava;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Fanava extends AbstractProvider
{

    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://fanava.shaparak.ir/ws/PaymentService/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://fanava.shaparak.ir/IPG';

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::FANAVA;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'Terminal'       => $this->config['terminal-id'],
            'RequestType'    => $transaction->getExtraField('request_type', 'PU'),
            'InvoiceId'      => $transaction->getOrderId(),
            'Amount'         => $transaction->getAmount()->getRiyal(),
            'CallBackUrl'    => $this->getCallback($transaction),
            'AdditionalData' => $transaction->getExtraField('additional_data'),
            'SettleModel'    => $transaction->getExtraField('settle_model'),
        ];
        $mobile = $transaction->getExtraField('mobile');
        if (! empty($mobile)) {
            $fields['CellNumber'] = substr($mobile, 1);
        }

        $token = $this->callApi('GetToken', $fields)->Token;

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, self::GATE_URL, [
            'Token' => $token,
        ]);

        return AuthorizedTransaction::make($transaction, null, $token, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('RespCode')) {
            throw new InvalidRequestException();
        }

        $code = $request->input('RespCode');
        $message = $request->input('RespMsg');

        if ($code != 0) {
            throw new FanavaException($code, $message);
        }

        $invoiceId = $request->input('InvoiceId');
        $amount = $request->input('Amount');

        return new FieldsToMatch($invoiceId, null, null, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $digitalReceipt = $request->input('digitalReceipt');
        $cardNumber = $request->input('CardNumber');
        $RRN = $request->input('RRN');

        $fields = [
            'Terminal'       => $this->config['terminal-id'],
            'DigitalReceipt' => $digitalReceipt,
        ];

        $response = $this->callApi('Advice', $fields)->RespCode;
        $amount = explode(';', $response)[1];

        $toMatch = new FieldsToMatch(null, null, null, new Amount($amount, 'IRR'));

        return new SettledTransaction($transaction, $digitalReceipt, $toMatch, $cardNumber, $RRN);
    }

    /**
     * @param string $digitalReceipt
     * @return mixed
     * @throws FanavaException
     */
    public function rollback(string $digitalReceipt)
    {
        $fields = [
            'Terminal'       => $this->config['terminal-id'],
            'DigitalReceipt' => $digitalReceipt,
        ];

        return $this->callApi('Rollback', $fields);
    }

    /**
     * @param string $path
     * @param array $fields
     * @return mixed
     * @throws FanavaException
     */
    protected function callApi(string $path, array $fields)
    {
        list($response, $http_code) = Curl::execute(self::SERVER_URL.$path, $fields, false);

        $code = explode(';', $response->RespCode)[0];
        if ($http_code != 200 || $code != 0 || $code != 1) {
            throw new FanavaException($code ?? $http_code, $response->RespMsg ?? null);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'          => '09124441122',
            'additional_data' => '(json) داده‌های اضافی',
            'settle_model'    => '(json) مدل تسویه (نکته: در فاز بعدی توسعه داده می‌شود)',
            'request_type'    => 'PU (default) (خرید) || '.
                'BI (قبض) || '.
                'CH (شارژ) || '.
                'BL (موجودی) || '.
                'UD (نامشخص: برای سرویس‌هایی مانند کمک‌های مردمی و غیره استفاده شود)',
        ];
    }
}
