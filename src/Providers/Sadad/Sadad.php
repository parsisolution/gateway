<?php

namespace Parsisolution\Gateway\Providers\Sadad;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Sadad extends AbstractProvider
{
    /**
     * Url of sadad gateway web service
     *
     * @var string
     */
    const SERVER_URL = 'https://sadad.shaparak.ir/api/v0';

    /**
     * Url of sadad gateway redirect path
     *
     * @var string
     */
    const GATE_URL = 'https://sadad.shaparak.ir/Purchase?Token=';

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $TerminalId = $this->config['terminal-id'];
        $OrderId = $transaction->getOrderId();
        $Amount = $transaction->getAmount()->getRiyal();
        $SignData = $this->encryptPKCS7("$TerminalId;$OrderId;$Amount", $this->config['terminal-key']);
        $fields = [
            'MerchantId'       => $this->config['merchant-id'],
            'TerminalId'       => $TerminalId,
            'Amount'           => $Amount,
            'OrderId'          => $OrderId,
            'LocalDateTime'    => date('m/d/Y g:i:s a'),
            'ReturnUrl'        => $this->getCallback($transaction),
            'SignData'         => $SignData,
            'AdditionalData'   => $transaction->getExtraField('description'),
            'MultiplexingData' => $transaction->getExtraField('multiplexing_data'),
            'ApplicationName'  => $transaction->getExtraField('application_name'),
        ];
        $mobile = $transaction->getExtraField('mobile');
        if (! empty($mobile)) {
            $fields['UserId'] = '98'.substr($mobile, 1);
        }

        [$response] = Curl::execute(self::SERVER_URL.'/Request/PaymentRequest', $fields, false);

        if ($response->ResCode != 0) {
            throw new SadadException($response->ResCode, $response->Description);
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, self::GATE_URL.$response->Token);

        return AuthorizedTransaction::make($transaction, null, $response->Token, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $ResCode = $request->input('ResCode');
        //        $SwitchResCode = $request->input('SwitchResCode');

        if ($ResCode != 0) {
            throw new InvalidRequestException();
        }

        $OrderId = $request->input('OrderId');
        $Token = $request->input('Token');

        return new FieldsToMatch($OrderId, null, $Token);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $masked_card_number = $request->input('PrimaryAccNo');
        $hashed_card_number = $request->input('HashedCardNo');

        $fields = [
            'Token'    => $transaction->getToken(),
            'SignData' => $this->encryptPKCS7($transaction->getToken(), $this->config['terminal-key']),
        ];

        [$response] = Curl::execute(self::SERVER_URL.'/Advice/Verify', $fields);

        if ($response['ResCode'] != 0) {
            throw new SadadException($response['ResCode'], $response['Description']);
        }

        $orderId = $response['OrderId'];
        $amount = $response['Amount'];
        $traceNumber = $response['SystemTraceNo'];
        $cardNumber = $response['CustomerCardNumber'];
        $RRN = $response['RetrivalRefNo'];

        return new SettledTransaction(
            $transaction,
            $traceNumber,
            new FieldsToMatch($orderId, null, null, new Amount($amount, 'IRR')),
            $cardNumber,
            $RRN,
            compact('masked_card_number', 'hashed_card_number')
        );
    }

    /**
     * Create signed data (TripleDES(ECB,PKCS7))
     *
     * @param  string  $str
     * @param  string  $key
     * @return string
     */
    private function encryptPKCS7($str, $key)
    {
        $key = base64_decode($key);
        $cipherText = openssl_encrypt($str, 'DES-EDE3', $key, OPENSSL_RAW_DATA);

        return base64_encode($cipherText);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'            => '09124441122',
            'description'       => 'اطلاعات اضافی تراکنش',
            'multiplexing_data' => [
                'Type'             => 'Amount || Percentage',
                'MultiplexingRows' => [
                    [
                        'IbanNumber' => 'رديف يا شماره شبا حساب همراه IR',
                        'Value'      => '(integer) مبلغ يا درصد',
                    ],
                    [
                        'IbanNumber' => 'رديف يا شماره شبا حساب همراه IR',
                        'Value'      => '(integer) مبلغ يا درصد',
                    ],
                ],
            ],
            'application_name' => 'نام اپلیکیشن درخواست کننده - '.
                'اختیاری (برای گزارشات لازم است که اين فیلد مقدار دهی شود)',
        ];
    }
}
