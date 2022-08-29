<?php

namespace Parsisolution\Gateway\Providers\Irankish;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Irankish extends AbstractProvider
{

    /**
     * Address of main server
     *
     * @var string
     */
    const SERVER_URL = 'https://ikc.shaparak.ir/api/v3/tokenization/make';

    /**
     * Address of SOAP server for verify payment
     *
     * @var string
     */
    const SERVER_VERIFY_URL = 'https://ikc.shaparak.ir/api/v3/confirmation/purchase';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://ikc.shaparak.ir/iuiv3/IPG/Index';

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::IRANKISH;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'request'                => [
                'acceptorId'        => $this->config['acceptor-id'],
                'amount'            => $transaction->getAmount()->getRiyal(),
                'billInfo'          => null,
                'requestId'         => $transaction->getOrderId(),
                'paymentId'         => $transaction->getOrderId(),
                'cmsPreservationId' => '98'.substr($transaction->getExtraField('mobile'), 1),
                'requestTimestamp'  => time(),
                'revertUri'         => $this->getCallback($transaction),
                'terminalId'        => $this->config['terminal-id'],
                'transactionType'   => 'Purchase',
            ],
            'authenticationEnvelope' => $this->generateAuthenticationEnvelope($transaction->getAmount()->getRiyal()),
        ];

        list($response) = Curl::execute(self::SERVER_URL, $fields);

        $data = ['tokenIdentity' => $response['result']['token']];

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, self::GATE_URL, $data);

        return AuthorizedTransaction::make($transaction, null, $response['result']['token'], $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $responseCode = $request->input('responseCode');

        if ($responseCode != '0' && $responseCode != '00') {
            throw new IrankishException($responseCode);
        }

        $orderId = $request->input('paymentId');
        $token = $request->input('token');
        $amount = $request->input('amount');

        return new FieldsToMatch($orderId, null, $token, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'terminalId'               => $this->config['terminal-id'],
            'retrievalReferenceNumber' => $request->input('retrievalReferenceNumber'),
            'systemTraceAuditNumber'   => $request->input('systemTraceAuditNumber'),
            'tokenIdentity'            => $request->input('token'),
        ];

        list($response) = Curl::execute(self::SERVER_VERIFY_URL, $fields);

        if ($response['responseCode'] != '0' && $response['responseCode'] != '00') {
            throw new IrankishException($response['responseCode']);
        }

        $result = $response['result'];

        $extraFields = [
            'status'               => $response['status'],
            'provider_description' => $response['description'],
            'sha256OfPan'          => $request->input('sha256OfPan'),
        ];

        $toMatch = new FieldsToMatch($result['paymentId'], null, null, new Amount($result['amount'], 'IRR'));

        return new SettledTransaction(
            $transaction,
            $result['systemTraceAuditNumber'],
            $toMatch,
            $request->input('maskedPan'),
            $result['retrievalReferenceNumber'],
            $extraFields
        );
    }

    /**
     * @param float $amount
     * @return array
     */
    protected function generateAuthenticationEnvelope(float $amount): array
    {
        $data = $this->config['terminal-id'].$this->config['password'].str_pad($amount, 12, '0', STR_PAD_LEFT).'00';
        $data = hex2bin($data);
        $AESSecretKey = openssl_random_pseudo_bytes(16);
        $iv_length = openssl_cipher_iv_length($cipher = 'AES-128-CBC');
        $iv = openssl_random_pseudo_bytes($iv_length);
        $ciphertext_raw = openssl_encrypt($data, $cipher, $AESSecretKey, OPENSSL_RAW_DATA, $iv);
        $hmac = hash('sha256', $ciphertext_raw, true);
        $encrypted_data = '';

        openssl_public_encrypt($AESSecretKey.$hmac, $encrypted_data, $this->config['public-key']);

        return [
            'data' => bin2hex($encrypted_data),
            'iv'   => bin2hex($iv),
        ];
    }
}
