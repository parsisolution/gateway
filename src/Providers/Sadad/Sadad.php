<?php

namespace Parsisolution\Gateway\Providers\Sadad;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
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
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
    {
        return GatewayManager::SADAD;
    }

    /**
     * Authorize payment request from provider's server and return
     * authorization response as AuthorizedTransaction
     * or throw an Error (most probably SoapFault)
     *
     * @param UnAuthorizedTransaction $transaction
     * @return AuthorizedTransaction
     * @throws Exception
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $key = $this->config['key'];
        $TerminalId = $this->config['terminalId'];
        $Amount = $transaction->getAmount()->getRiyal();
        $OrderId = $transaction->getId();
        $SignData = $this->encryptPKCS7("$TerminalId;$OrderId;$Amount", "$key");
        $data = [
            'TerminalId'    => $TerminalId,
            'MerchantId'    => $this->config['merchantId'],
            'Amount'        => $Amount,
            'SignData'      => $SignData,
            'ReturnUrl'     => $this->getCallback($transaction),
            'LocalDateTime' => date("m/d/Y g:i:s a"),
            'OrderId'       => $transaction->getId(),
        ];
        $str_data = json_encode($data);
        $res = $this->callAPI(self::SERVER_URL.'/Request/PaymentRequest', $str_data);
        $response = json_decode($res);
        if ($response->ResCode != 0) {
            throw new SadadException($response->ResCode, $response->Description);
        }

        return AuthorizedTransaction::make($transaction, $response->Token);
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        return new RedirectResponse(self::GATE_URL.$transaction->getReferenceId());
    }

    /**
     * Validate the settlement request to see if it has all necessary fields
     *
     * @param Request $request
     * @return bool
     * @throws TransactionException
     */
    protected function validateSettlementRequest(Request $request)
    {
        $ResCode = $request->input("ResCode");

        if ($ResCode == 0) {
            return true;
        }

        throw new SadadException($ResCode);
    }

    /**
     * Verify and Settle the transaction and return
     * settlement response as SettledTransaction
     * or throw a TransactionException
     *
     * @param Request $request
     * @param AuthorizedTransaction $transaction
     * @return SettledTransaction
     * @throws TransactionException
     * @throws Exception
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $key = $this->config['key'];
        $Token = $request->input("token");

        $verifyData = array('Token' => $Token, 'SignData' => $this->encryptPKCS7($Token, $key));
        $str_data = json_encode($verifyData);
        $res = $this->callAPI(self::SERVER_URL.'/Advice/Verify', $str_data);
        $response = json_decode($res);

        if ($response->ResCode != -1 && $response->ResCode == 0) {
            $trackingCode = $response->SystemTraceNo;
            $cardNumber = $response->CustomerCardNumber;

            return new SettledTransaction($transaction, $trackingCode, $cardNumber, json_decode($res, true));
        }

        throw new SadadException(
            $response->ResCode,
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

    /**
     * @param string $url
     * @param mixed $data
     * @return string
     */
    private function callAPI($url, $data = false)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array('Content-Type: application/json', 'Content-Length: '.strlen($data))
        );
        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }
}
