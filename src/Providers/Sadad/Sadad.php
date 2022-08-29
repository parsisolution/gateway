<?php

namespace Parsisolution\Gateway\Providers\Sadad;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
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
    const SERVER_URL = 'https://sadad.shaparak.ir/vpg/api/v0';

    /**
     * Url of sadad gateway redirect path
     *
     * @var string
     */
    const GATE_URL = 'https://sadad.shaparak.ir/VPG/Purchase?Token=';

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::SADAD;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $key = $this->config['key'];
        $TerminalId = $this->config['terminalId'];
        $Amount = $transaction->getAmount()->getRiyal();
        $OrderId = $transaction->getOrderId();
        $SignData = $this->encryptPKCS7("$TerminalId;$OrderId;$Amount", "$key");
        $fields = [
            'TerminalId'    => $TerminalId,
            'MerchantId'    => $this->config['merchantId'],
            'Amount'        => $Amount,
            'SignData'      => $SignData,
            'ReturnUrl'     => $this->getCallback($transaction),
            'LocalDateTime' => date("m/d/Y g:i:s a"),
            'OrderId'       => $transaction->getOrderId(),
        ];

        list($response) = Curl::execute(self::SERVER_URL.'/Request/PaymentRequest', $fields, false);

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
        $ResCode = $request->input("ResCode");

        if ($ResCode != 0) {
            throw new SadadException($ResCode);
        }

        $Token = $request->input("token");

        return new FieldsToMatch(null, null, $Token);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $key = $this->config['key'];

        $fields = [
            'Token'    => $transaction->getToken(),
            'SignData' => $this->encryptPKCS7($transaction->getToken(), $key),
        ];

        list($response) = Curl::execute(self::SERVER_URL.'/Advice/Verify', $fields);

        if ($response['ResCode'] != -1 && $response['ResCode'] == 0) {
            $traceNumber = $response['SystemTraceNo'];
            $cardNumber = $response['CustomerCardNumber'];
            $RRN = $response['RetrivalRefNo'];

            return new SettledTransaction(
                $transaction,
                $traceNumber,
                $cardNumber,
                $RRN,
                $response
            );
        }

        throw new SadadException(
            $response['ResCode'],
            "تراکنش نا موفق بود در صورت کسر مبلغ از حساب شما حداکثر پس از 72 ساعت مبلغ به حسابتان برمی گردد."
        );
    }

    /**
     * Create signed data (TripleDES(ECB,PKCS7))
     *
     * @param string $str
     * @param string $key
     * @return string
     */
    private function encryptPKCS7($str, $key)
    {
        $key = base64_decode($key);
        $cipherText = OpenSSL_encrypt($str, "DES-EDE3", $key, OPENSSL_RAW_DATA);

        return base64_encode($cipherText);
    }
}
