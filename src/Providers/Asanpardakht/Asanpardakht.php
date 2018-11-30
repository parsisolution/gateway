<?php

namespace Parsisolution\Gateway\Providers\Asanpardakht;

use Exception;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Asanpardakht extends AbstractProvider
{

    /**
     * Address of main SOAP server
     *
     * @var string
     */
    const SERVER_URL = 'https://services.asanpardakht.net/paygate/merchantservices.asmx?wsdl';
    const SERVER_UTILS = 'https://services.asanpardakht.net/paygate/internalutils.asmx?WSDL';

    /**
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
    {
        return GatewayManager::ASANPARDAKHT;
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
        $username = $this->config['username'];
        $password = $this->config['password'];
        $orderId = $this->transaction->getId();
        $price = $transaction->getAmount()->getRiyal();
        $localDate = date("Ymd His");
        $additionalData = "";
        $callBackUrl = $this->getCallback($transaction);
        $req = "1,{$username},{$password},{$orderId},{$price},{$localDate},{$additionalData},{$callBackUrl},0";

        $encryptedRequest = $this->encrypt($req);
        $params = array(
            'merchantConfigurationID' => $this->config['merchantConfigId'],
            'encryptedRequest'        => $encryptedRequest,
        );

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->RequestOperation($params);

        $response = $response->RequestOperationResult;
        $responseCode = explode(",", $response)[0];
        if ($responseCode != '0') {
            throw new AsanpardakhtException($response);
        }

        return AuthorizedTransaction::make($transaction, substr($response, 2));
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        return $this->view('gateway::asan-pardakht-redirector')->with([
            'refId' => $transaction->getReferenceId(),
        ]);
    }

    /**
     * Validate the settlement request to see if it has all necessary fields
     *
     * @param Request $request
     * @return bool
     * @throws InvalidRequestException
     */
    protected function validateSettlementRequest(Request $request)
    {
        $ReturningParams = $request->input('ReturningParams');

        if (isset($ReturningParams)) {
            return true;
        }

        throw new InvalidRequestException();
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
        $ReturningParams = $request->input('ReturningParams');
        $ReturningParams = $this->decrypt($ReturningParams);

        $paramsArray = explode(",", $ReturningParams);
        $Amount = $paramsArray[0];
        $SaleOrderId = $paramsArray[1];
        $RefId = $paramsArray[2];
        $ResCode = $paramsArray[3];
        $ResMessage = $paramsArray[4];
        $PayGateTranID = $paramsArray[5];
        $RRN = $paramsArray[6];
        $LastFourDigitOfPAN = $paramsArray[7];

        $settledTransaction = new SettledTransaction($transaction, $PayGateTranID, $LastFourDigitOfPAN);

        if (! ($ResCode == '0' || $ResCode == '00')) {
            throw new AsanpardakhtException($ResCode);
        }


        $username = $this->config['username'];
        $password = $this->config['password'];

        $encryptedCredintials = $this->encrypt("{$username},{$password}");
        $params = array(
            'merchantConfigurationID' => $this->config['merchantConfigId'],
            'encryptedCredentials'    => $encryptedCredintials,
            'payGateTranID'           => $settledTransaction->getTrackingCode(),
        );


        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->RequestVerification($params);
        $response = $response->RequestVerificationResult;

        if ($response != '500') {
            throw new AsanpardakhtException($response);
        }

        $response = $soap->RequestReconciliation($params);
        $response = $response->RequestReconciliationResult;

        if ($response != '600') {
            throw new AsanpardakhtException($response);
        }

        return $settledTransaction;
    }

    /**
     * Encrypt string by key and iv from config
     *
     * @param string $string
     * @return string
     */
    private function encrypt($string = "")
    {

        $key = $this->config['key'];
        $iv = $this->config['iv'];

        try {
            $soap = new SoapClient(self::SERVER_UTILS, $this->soapConfig());
            $params = array(
                'aesKey'        => $key,
                'aesVector'     => $iv,
                'toBeEncrypted' => $string,
            );

            $response = $soap->EncryptInAES($params);

            return $response->EncryptInAESResult;
        } catch (\SoapFault $e) {
            return "";
        }
    }

    /**
     * Decrypt string by key and iv from config
     *
     * @param string $string
     * @return string
     */
    private function decrypt($string = "")
    {
        $key = $this->config['key'];
        $iv = $this->config['iv'];

        try {
            $soap = new SoapClient(self::SERVER_UTILS, $this->soapConfig());
            $params = array(
                'aesKey'        => $key,
                'aesVector'     => $iv,
                'toBeDecrypted' => $string,
            );

            $response = $soap->DecryptInAES($params);

            return $response->DecryptInAESResult;
        } catch (\SoapFault $e) {
            return "";
        }
    }
}
