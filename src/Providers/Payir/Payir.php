<?php

namespace Parsisolution\Gateway\Providers\Payir;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Payir extends AbstractProvider
{

    /**
     * Address of main CURL server
     *
     * @var string
     */
    const SERVER_URL = 'https://pay.ir/pg/send';

    /**
     * Address of CURL server for verify payment
     *
     * @var string
     */
    const SERVER_VERIFY_URL = 'https://pay.ir/pg/verify';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const URL_GATE = 'https://pay.ir/pg/';

    protected $factorNumber;

    /**
     * Set factor number (optional)
     *
     * @param $factorNumber
     *
     * @return $this
     */
    public function setFactorNumber($factorNumber)
    {
        $this->factorNumber = $factorNumber;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::PAYIR;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'api'             => $this->config['api-key'],
            'amount'          => $transaction->getAmount()->getRiyal(),
            'redirect'        => $this->getCallback($transaction, true),
            'mobile'          => $transaction->getExtraField('mobile'),
            'factorNumber'    => $this->factorNumber ?? $transaction->getExtraField('factor_number'),
            'description'     => $transaction->getExtraField('description'),
            'validCardNumber' => $transaction->getExtraField('valid_card_number'),
        ];

        list($response) = Curl::execute(self::SERVER_URL, $fields, true, [
            CURLOPT_SSL_VERIFYPEER => false,
        ], Curl::METHOD_GET);

        if (! is_numeric($response['status']) || $response['status'] <= 0) {
            throw new PayirSendException($response['errorCode'], $response['errorMessage']);
        }

        $redirectResponse = new RedirectResponse(
            RedirectResponse::TYPE_GET,
            self::URL_GATE.$response['token']
        );

        return AuthorizedTransaction::make($transaction, null, $response['token'], $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $status = $request->input('status');
        $message = $request->input('message');

        if (! is_numeric($status) || $status <= 0) {
            throw new PayirReceiveException(-5, $message);
        }

        $token = $request->input('token');

        return new FieldsToMatch(null, null, $token);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $traceNumber = $request->input('token');
        $cardNumber = $request->input('cardNumber');

        $fields = [
            'api'   => $this->config['api-key'],
            'token' => $transaction->getToken(),
        ];

        list($response) = Curl::execute(self::SERVER_VERIFY_URL, $fields, true, [
            CURLOPT_SSL_VERIFYPEER => false,
        ], Curl::METHOD_GET);

        if ($response['status'] != 1) {
            throw new PayirReceiveException($response['errorCode'], $response['errorMessage']);
        }

        $toMatch = new FieldsToMatch();

        return new SettledTransaction($transaction, $traceNumber, $toMatch, $cardNumber);
    }
}
