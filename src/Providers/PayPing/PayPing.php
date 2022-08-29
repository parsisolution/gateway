<?php

namespace Parsisolution\Gateway\Providers\PayPing;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class PayPing extends AbstractProvider
{

    /**
     * Address of main CURL server
     *
     * @var string
     */
    const SERVER_URL = 'https://api.payping.ir/v2/pay';

    /**
     * Address of CURL server for verify payment
     *
     * @var string
     */
    const SERVER_VERIFY_URL = 'https://api.payping.ir/v2/pay/verify';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const URL_GATE = 'https://api.payping.ir/v2/pay/gotoipg/';


    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::PAYPING;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'clientRefId'   => $transaction->getOrderId(),
            'payerIdentity' => $transaction->getExtraField('mobile'),
            'Amount'        => $transaction->getAmount()->getToman(),
            'Description'   => $transaction->getExtraField('description'),
            'returnUrl'     => $this->getCallback($transaction),
        ];

        list($response, $http_code, $error) = Curl::execute(self::SERVER_URL, $fields, true, [
            CURLOPT_TIMEOUT    => 45,
            CURLOPT_HTTPHEADER => $this->generateHeaders(),
        ]);

        if ($error) {
            throw new PayPingException($error);
        }

        if ($http_code == 400) {
            throw new PayPingException(400, json_encode($response));
        } elseif ($http_code != 200) {
            throw new PayPingException($http_code);
        }

        if (empty($response)) {
            throw new PayPingException(200, 'تراکنش ناموفق بود - شرح خطا: عدم وجود کد ارجاع');
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, self::URL_GATE.$response['code']);

        return AuthorizedTransaction::make($transaction, $response['code'], null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $referenceId = $request->input('refid');

        if (! $referenceId) {
            throw new PayPingException($referenceId);
        }

        return new FieldsToMatch(null, $referenceId);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $refId = $request->input('refid');
        $cardNumber = $request->input('cardnumber');

        $fields = [
            'refId'  => $refId,
            'amount' => $transaction->getAmount()->getToman(),
        ];

        list($response, $http_code, $error) = Curl::execute(self::SERVER_VERIFY_URL, $fields, true, [
            CURLOPT_TIMEOUT    => 45,
            CURLOPT_HTTPHEADER => $this->generateHeaders(),
        ]);

        if ($error) {
            throw new PayPingException($error);
        }

        if ($http_code == 400) {
            throw new PayPingException(400, json_encode($response));
        } elseif ($http_code != 200) {
            throw new PayPingException($http_code);
        }

        if (empty($refId)) {
            throw new PayPingException(
                200,
                'متافسانه سامانه قادر به دریافت کد پیگیری نمی‌باشد! نتیجه درخواست: '.$http_code
            );
        }

        $toMatch = new FieldsToMatch();

        return new SettledTransaction($transaction, $refId, $toMatch, $cardNumber, '', $response);
    }

    /**
     * @return array
     */
    protected function generateHeaders(): array
    {
        return [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->config['api-key'],
            'Cache-Control: no-cache',
        ];
    }
}
