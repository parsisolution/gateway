<?php

namespace Parsisolution\Gateway\Providers\SabaPay;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class SabaPay extends AbstractProvider
{

    /**
     * Address of main CURL server
     *
     * @var string
     */
    const SERVER_URL = 'http://pay.sabanovin.com/invoice/request';

    /**
     * Address of CURL server for verify payment
     *
     * @var string
     */
    const SERVER_VERIFY_URL = 'http://pay.sabanovin.com/invoice/check/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const URL_GATE = 'http://pay.sabanovin.com/invoice/pay/';


    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::SABAPAY;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'api_key'    => $this->config['api-key'],
            'amount'     => $transaction->getAmount()->getToman(),
            'return_url' => $this->getCallback($transaction, true),
        ];

        list($response) = Curl::execute(self::SERVER_URL, $fields, true, [
            CURLOPT_SSL_VERIFYPEER => false,
        ], Curl::METHOD_GET);

        if ($response['status'] != 1) {
            throw new SabaPayException($response['errorCode']);
        }

        $redirectResponse = new RedirectResponse(
            RedirectResponse::TYPE_GET,
            self::URL_GATE.$response['invoice_key']
        );

        return AuthorizedTransaction::make($transaction, $response['invoice_key'], null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $status = $request->input('status');

        if ($status != 0) {
            throw new SabaPayException($status);
        }

        $referenceId = $request->input('invoice_key');

        return new FieldsToMatch(null, $referenceId);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $traceNumber = $request->input('bank_code');
        $cardNumber = $request->input('card_number');

        $fields = [
            'api_key' => $this->config['api-key'],
        ];

        list($response) = Curl::execute(self::SERVER_VERIFY_URL.$transaction->getReferenceId(), $fields, true, [
            CURLOPT_SSL_VERIFYPEER => false,
        ], Curl::METHOD_GET);

        if ($response['status'] != 1) {
            throw new SabaPayException($response['errorCode']);
        }

        $toMatch = new FieldsToMatch();

        return new SettledTransaction($transaction, $traceNumber, $toMatch, $cardNumber);
    }
}
