<?php

namespace Parsisolution\Gateway\Providers\MabnaOld;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
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
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://mabna.shaparak.ir';

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
        $data = $transaction->getAmount()->getRiyal().$transaction->getOrderId().$this->config['merchant-id'].
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
        $data = $this->config['merchant-id'].$transaction->getTraceNumber().$transaction->getOrderId();

        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA1);

        return base64_encode($signature);
    }

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::MABNA_OLD;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            "Token_param" => [
                "AMOUNT"        => $this->encryptData($transaction->getAmount()->getRiyal()),
                "CRN"           => $this->encryptData($transaction->getOrderId()),
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

        $token = $response->return->token;

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, self::GATE_URL, [
            'TOKEN' => $token,
        ]);

        return AuthorizedTransaction::make($transaction, null, $token, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (empty($_POST) || $request->input('RESCODE') != '00') {
            throw new MabnaOldException($request->input('RESCODE'));
        }

        return new FieldsToMatch();
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $traceNumber = $request->input('TRN');

        $fields = [
            "SaleConf_req" => [
                "CRN"       => $this->encryptData($transaction->getOrderId()),
                "MID"       => $this->encryptData($this->config['merchant-id']),
                "TRN"       => $this->encryptData($traceNumber),
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

        $toMatch = new FieldsToMatch(null, null, null, new Amount($response->return->AMOUNT, 'IRR'));

        return new SettledTransaction($transaction, $traceNumber, $toMatch);
    }
}
