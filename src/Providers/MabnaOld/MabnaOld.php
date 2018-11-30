<?php

namespace Parsisolution\Gateway\Providers\MabnaOld;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class MabnaOld extends AbstractProvider
{

    /**
     * Address of main SOAP server
     *
     * @var string
     */
    const SERVER_URL = "https://mabna.shaparak.ir/TokenService?wsdl";

    /**
     * Address of verify SOAP server
     *
     * @var string
     */
    const SERVER_VERIFY_URL = "https://mabna.shaparak.ir/TransactionReference/TransactionReference?wsdl";

    /**
     * Public key
     *
     * @var mixed
     */
    private $publicKey;

    /**
     * Private key
     *
     * @var mixed
     */
    private $privateKey;

    public function __construct(Container $app, array $config)
    {
        parent::__construct($app, $config);

        $this->setKeys();
    }

    /**
     * Generate public and private keys
     *
     * @return void
     */
    protected function setKeys()
    {
        $pub_key = file_get_contents($this->config['public-key']);
        $pub_key = '-----BEGIN PUBLIC KEY-----'.PHP_EOL.$pub_key.PHP_EOL."-----END PUBLIC KEY-----";
        $this->publicKey = openssl_pkey_get_public($pub_key);

        $pri_key = file_get_contents($this->config['private-key']);
        $pri_key = "-----BEGIN PRIVATE KEY-----".PHP_EOL.$pri_key.PHP_EOL."-----END PRIVATE KEY-----";
        $this->privateKey = openssl_pkey_get_private($pri_key);
    }

    /**
     * Encrypt data field
     *
     * @param string $data
     * @return string
     */
    protected function encryptData($data)
    {
        openssl_public_encrypt($data, $crypted, $this->publicKey);

        return base64_encode($crypted);
    }

    /**
     * Create and encrypt signature
     *
     * @param UnAuthorizedTransaction $transaction
     * @return string
     */
    protected function createSignature(UnAuthorizedTransaction $transaction)
    {
        $data = $transaction->getAmount()->getRiyal().$transaction->getId().$this->config['merchant-id'].
            $this->getCallback($transaction).
            $this->config['terminal-id'];

        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA1);

        return base64_encode($signature);
    }

    /**
     * Create and encrypt verify signature
     *
     * @param SettledTransaction $transaction
     * @return string
     */
    protected function createVerifySignature(SettledTransaction $transaction)
    {
        $data = $this->config['merchant-id'].$transaction->getTrackingCode().$transaction->getId();

        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA1);

        return base64_encode($signature);
    }

    /**
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
    {
        return GatewayManager::MABNA_OLD;
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
        $fields = [
            "Token_param" => [
                "AMOUNT"        => $this->encryptData($transaction->getAmount()->getRiyal()),
                "CRN"           => $this->encryptData($transaction->getId()),
                "MID"           => $this->encryptData($this->config['merchant-id']),
                "REFERALADRESS" => $this->encryptData($this->getCallback($transaction)),
                "SIGNATURE"     => $this->createSignature($transaction),
                "TID"           => $this->encryptData($this->config['terminal-id']),
            ],
        ];

        // Disable SSL
        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig(), [
            "stream_context" => stream_context_create(
                [
                    'ssl' => [
                        'verify_peer'      => false,
                        'verify_peer_name' => false,
                    ],
                ]
            ),
        ]);
        $response = $soap->reservation($fields);

        if ($response->return->result != 0) {
            throw new MabnaOldException($response->return->result);
        }

        $result = openssl_verify(
            $response->return->token,
            base64_decode($response->return->signature),
            $this->publicKey
        );

        if ($result != 1) {
            throw new MabnaOldException('gateway-faild-signature-verify');
        }

        $refId = $response->return->token;

        return AuthorizedTransaction::make($transaction, $refId);
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        $token = $transaction->getReferenceId();

        return $this->view('gateway::mabna-old-redirector')->with(compact('token'));
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
        if (empty($_POST) || $request->input('RESCODE') != '00') {
            throw new MabnaOldException($request->input('RESCODE'));
        }

        return true;
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
        $trackingCode = $request->input('TRN');

        $fields = [
            "SaleConf_req" => [
                "CRN"       => $this->encryptData($transaction->getId()),
                "MID"       => $this->encryptData($this->config['merchant-id']),
                "TRN"       => $this->encryptData($trackingCode),
                "SIGNATURE" => $this->createSignature($transaction->generateUnAuthorized()),
            ],
        ];

        // Disable SSL
        $soap = new SoapClient(self::SERVER_VERIFY_URL, $this->soapConfig(), [
            "stream_context" => stream_context_create(
                [
                    'ssl' => [
                        'verify_peer'      => false,
                        'verify_peer_name' => false,
                    ],
                ]
            ),
        ]);
        $response = $soap->sendConfirmation($fields);

        if (empty($_POST) || $request->input('RESCODE') != '00') {
            throw new MabnaOldException($request->input('RESCODE'));
        }

        if ($response->return->RESCODE != '00') {
            throw new MabnaOldException($response->return->RESCODE);
        }

        $data = $response->return->RESCODE.$response->return->REPETETIVE.$response->return->AMOUNT.
            $response->return->DATE.$response->return->TIME.$response->return->TRN.$response->return->STAN;

        $result = openssl_verify($data, base64_decode($response->return->SIGNATURE), $this->publicKey);

        if ($result != 1) {
            throw new MabnaOldException('gateway-faild-signature-verify');
        }

        return new SettledTransaction($transaction, $trackingCode);
    }
}
