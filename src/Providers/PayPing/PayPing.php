<?php

namespace Parsisolution\Gateway\Providers\PayPing;

use Exception;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
    {
        return GatewayManager::PAYPING;
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
            'clientRefId'   => $transaction->getId(),
            'payerIdentity' => $transaction->getExtraField('mobile'),
            'Amount'        => $transaction->getAmount()->getRiyal(),
            'Description'   => $transaction->getExtraField('description'),
            'returnUrl'     => $this->getCallback($transaction),
        ];

        $curl = curl_init();
        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL            => self::SERVER_URL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "POST",
                CURLOPT_POSTFIELDS     => json_encode($fields),
                CURLOPT_HTTPHEADER     => [
                    "accept: application/json",
                    "authorization: Bearer ".$this->config['api'],
                    "cache-control: no-cache",
                    "content-type: application/json",
                ],
            ]
        );
        $response = curl_exec($curl);

        $header = curl_getinfo($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new PayPingException($err);
        }

        if ($header['http_code'] == 200) {
            $response = json_decode($response, true);
            if (isset($response) and $response != '') {
                return AuthorizedTransaction::make($transaction, $response['code']);
            } else {
                throw new PayPingException(200, 'تراکنش ناموفق بود - شرح خطا: عدم وجود کد ارجاع');
            }
        } elseif ($header['http_code'] == 400) {
            throw new PayPingException(400, $response);
        } else {
            throw new PayPingException($header['http_code']);
        }
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        return new RedirectResponse(self::URL_GATE.$transaction->getReferenceId());
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
        $refId = $request->input('refid');

        if ($refId) {
            return true;
        }

        throw new PayPingException($refId);
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
        $refId = $request->input('refid');
        $amount = $request->input('amount');

        $fields = [
            'refId'  => $refId,
            'amount' => $amount,
        ];

        $curl = curl_init();
        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL            => self::SERVER_VERIFY_URL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "POST",
                CURLOPT_POSTFIELDS     => json_encode($fields),
                CURLOPT_HTTPHEADER     => [
                    "accept: application/json",
                    "authorization: Bearer ".$this->config['api'],
                    "cache-control: no-cache",
                    "content-type: application/json",
                ],
            ]
        );
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $header = curl_getinfo($curl);
        curl_close($curl);

        if ($err) {
            throw new PayPingException($err);
        }

        if ($header['http_code'] == 200) {
            $response = json_decode($response, true);
            if (isset($refid) and $refid != '') {
                return new SettledTransaction($transaction, $refId, '', $response);
            } else {
                throw new PayPingException(
                    200,
                    'متافسانه سامانه قادر به دریافت کد پیگیری نمی‌باشد! نتیجه درخواست: '.$header['http_code']
                );
            }
        } elseif ($header['http_code'] == 400) {
            throw new PayPingException(400, $response);
        } else {
            throw new PayPingException($header['http_code']);
        }
    }
}
