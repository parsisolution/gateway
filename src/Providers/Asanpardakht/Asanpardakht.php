<?php

namespace Parsisolution\Gateway\Providers\Asanpardakht;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
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
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://asan.shaparak.ir';

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::ASANPARDAKHT;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $username = $this->config['username'];
        $password = $this->config['password'];
        $orderId = $transaction->getOrderId();
        $price = $transaction->getAmount()->getRiyal();
        $localDate = date("Ymd His");
        $additionalData = "";
        $callBackUrl = $this->getCallback($transaction);
        $req = "1,{$username},{$password},{$orderId},{$price},{$localDate},{$additionalData},{$callBackUrl},0";

        $encryptedRequest = $this->encrypt($req);
        $params = [
            'merchantConfigurationID' => $this->config['merchantConfigId'],
            'encryptedRequest'        => $encryptedRequest,
        ];

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->RequestOperation($params);

        $response = $response->RequestOperationResult;
        $responseCode = explode(",", $response)[0];
        if ($responseCode != '0') {
            throw new AsanpardakhtException($response);
        }

        $referenceId = substr($response, 2);

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, self::GATE_URL, [
            'RefId' => $referenceId,
        ]);

        return AuthorizedTransaction::make($transaction, $referenceId, null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $ReturningParams = $request->input('ReturningParams');

        if (! isset($ReturningParams)) {
            throw new InvalidRequestException();
        }

        $ReturningParams = $request->input('ReturningParams');

        list($amount, $orderId, $referenceId) = explode(",", $this->decrypt($ReturningParams));

        return new FieldsToMatch($orderId, $referenceId, null, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $ReturningParams = $request->input('ReturningParams');

        list($Amount, $SaleOrderId, $RefId, $ResCode, $ResMessage, $PayGateTranID, $RRN, $LastFourDigitOfPAN) =
            explode(',', $this->decrypt($ReturningParams));

        $cardNumber = '************'.$LastFourDigitOfPAN;
        if (substr($ResMessage, 0, 27) === 'FirstSixDigitsOfCardNumber:') {
            $cardNumber = substr($ResMessage, 27).'******'.$LastFourDigitOfPAN;
        }

        if (! ($ResCode == '0' || $ResCode == '00')) {
            throw new AsanpardakhtException($ResCode);
        }

        $username = $this->config['username'];
        $password = $this->config['password'];

        $encryptedCredintials = $this->encrypt("{$username},{$password}");
        $params = [
            'merchantConfigurationID' => $this->config['merchantConfigId'],
            'encryptedCredentials'    => $encryptedCredintials,
            'payGateTranID'           => $PayGateTranID,
        ];

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

        return new SettledTransaction($transaction, $PayGateTranID, new FieldsToMatch(), $cardNumber, $RRN);
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
            $params = [
                'aesKey'        => $key,
                'aesVector'     => $iv,
                'toBeEncrypted' => $string,
            ];

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
            $params = [
                'aesKey'        => $key,
                'aesVector'     => $iv,
                'toBeDecrypted' => $string,
            ];

            $response = $soap->DecryptInAES($params);

            return $response->DecryptInAESResult;
        } catch (\SoapFault $e) {
            return "";
        }
    }
}
