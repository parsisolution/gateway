<?php

namespace Parsisolution\Gateway\Providers\AsanPardakht;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\ApiType;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class AsanPardakht extends AbstractProvider
{

    /**
     * Address of main SOAP server
     *
     * @var string
     */
    const SERVER_SOAP_URL = 'https://ipgsoap.asanpardakht.ir/paygate/merchantservices.asmx?wsdl';
    const SERVER_UTILS = 'https://ipgsoap.asanpardakht.ir/paygate/internalutils.asmx?wsdl';

    /**
     * Address of main REST server
     *
     * @var string
     */
    const SERVER_REST_URL = 'https://ipgrest.asanpardakht.ir/v1';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://asan.shaparak.ir';

    /**
     * Type of api to use
     *
     * @var string
     */
    protected $apiType;

    public function __construct(Container $app, array $config)
    {
        parent::__construct($app, $config);

        $this->apiType = Arr::get($config, 'api-type', ApiType::SOAP_CLIENT);
    }

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
        $amount = $transaction->getAmount()->getRiyal();
        $localDate = date('Ymd His');
        $additionalData = $transaction->getExtraField('description', '');
        $callBackUrl = $this->getCallback($transaction);

        if ($this->apiType == ApiType::SOAP_CLIENT) {
            $req = "1,{$username},{$password},{$orderId},{$amount},{$localDate},{$additionalData},{$callBackUrl},0";

            $encryptedRequest = $this->encrypt($req);
            $params = [
                'merchantConfigurationID' => $this->config['merchant-config-id'],
                'encryptedRequest'        => $encryptedRequest,
            ];

            $soap = new SoapClient(self::SERVER_SOAP_URL, $this->soapConfig());
            $response = $soap->RequestOperation($params);

            $response = $response->RequestOperationResult;
            $responseCode = explode(',', $response)[0];
            if ($responseCode != '0') {
                throw new AsanPardakhtException($response);
            }

            $referenceId = substr($response, 2);
        } else {
            $fields = [
                'merchantConfigurationId' => $this->config['merchant-config-id'],
                'serviceTypeId'           => 1,
                'localInvoiceId'          => $orderId,
                'amountInRials'           => $amount,
                'localDate'               => $localDate,
                'additionalData'          => $additionalData,
                'callbackURL'             => $callBackUrl,
                'paymentId'               => '0',
            ];
            $settlementPortions = $transaction->getExtraField('settlement_portions');
            if ($settlementPortions) {
                $fields['settlementPortions'] = $settlementPortions;
            }

            list($referenceId, $http_code) = Curl::execute(self::SERVER_REST_URL.'/Token', $fields, true, [
                CURLOPT_HTTPHEADER => $this->generateHeaders(),
            ]);

            if ($http_code != 200) {
                throw new AsanPardakhtRestTokenException($http_code);
            }
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, self::GATE_URL, [
            'RefId'    => $referenceId,
            'mobileap' => $transaction->getExtraField('mobile'),
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

        list($amount, $orderId, $referenceId) = explode(',', $this->decrypt($ReturningParams));

        return new FieldsToMatch($orderId, $referenceId, null, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        if ($this->apiType == ApiType::SOAP_CLIENT) {
            $ReturningParams = $request->input('ReturningParams');

            list($Amount, $SaleOrderId, $RefId, $ResCode, $ResMessage, $PayGateTranID, $RRN, $LastFourDigitOfPAN) =
                explode(',', $this->decrypt($ReturningParams));

            $cardNumber = '************'.$LastFourDigitOfPAN;
            if (substr($ResMessage, 0, 27) === 'FirstSixDigitsOfCardNumber:') {
                $cardNumber = substr($ResMessage, 27).'******'.$LastFourDigitOfPAN;
            }

            if (! ($ResCode == '0' || $ResCode == '00')) {
                throw new AsanPardakhtException($ResCode);
            }

            $username = $this->config['username'];
            $password = $this->config['password'];

            $encryptedCredintials = $this->encrypt("{$username},{$password}");
            $params = [
                'merchantConfigurationID' => $this->config['merchant-config-id'],
                'encryptedCredentials'    => $encryptedCredintials,
                'payGateTranID'           => $PayGateTranID,
            ];

            $soap = new SoapClient(self::SERVER_SOAP_URL, $this->soapConfig());
            $response = $soap->RequestVerification($params);
            $response = $response->RequestVerificationResult;

            if ($response != '500') {
                throw new AsanPardakhtException($response);
            }

            $response = $soap->RequestReconciliation($params);
            $response = $response->RequestReconciliationResult;

            if ($response != '600') {
                throw new AsanPardakhtException($response);
            }

            $toMatch = new FieldsToMatch();
        } else {
            $fields = [
                'merchantConfigurationId' => $this->config['merchant-config-id'],
                'localInvoiceId'          => $transaction->getOrderId(),
            ];

            list($result, $http_code) = Curl::execute(self::SERVER_REST_URL.'/TranResult', $fields, true, [
                CURLOPT_HTTPHEADER => $this->generateHeaders(true),
            ], 'GET');

            if ($http_code != 200) {
                throw new AsanPardakhtRestException($http_code);
            }

            $PayGateTranID = $result['payGateTranID'];
            $RRN = $result['rrn'];
            $RefId = $result['refID'];
            $cardNumber = $result['cardNumber'];
            $SaleOrderId = $result['salesOrderID'];

            $fields = [
                'merchantConfigurationId' => $this->config['merchant-config-id'],
                'payGateTranId'           => $PayGateTranID,
            ];

            list(, $http_code) = Curl::execute(self::SERVER_REST_URL.'/Verify', $fields, true, [
                CURLOPT_HTTPHEADER => $this->generateHeaders(),
            ]);

            if ($http_code != 200) {
                throw new AsanPardakhtRestException($http_code);
            }

            list(, $http_code) = Curl::execute(self::SERVER_REST_URL.'/Settlement', $fields, true, [
                CURLOPT_HTTPHEADER => $this->generateHeaders(),
            ]);

            if ($http_code != 200) {
                throw new AsanPardakhtRestException($http_code);
            }

            $toMatch = new FieldsToMatch($SaleOrderId, $RefId);
        }

        return new SettledTransaction($transaction, $PayGateTranID, $toMatch, $cardNumber, $RRN);
    }

    /**
     * Encrypt string by key and iv from config
     *
     * @param string $string
     * @return string
     */
    private function encrypt($string = '')
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
            return '';
        }
    }

    /**
     * Decrypt string by key and iv from config
     *
     * @param string $string
     * @return string
     */
    private function decrypt($string = '')
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
            return '';
        }
    }

    /**
     * @param bool $forGetRequest
     * @return string[]
     */
    protected function generateHeaders(bool $forGetRequest = false): array
    {
        $headers = [
            'Accept: application/json',
            'usr: '.$this->config['username'],
            'pwd: '.$this->config['password'],
        ];

        if (! $forGetRequest) {
            $headers[] = 'Content-Type: application/json';
        }

        return $headers;
    }
}
