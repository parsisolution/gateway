<?php

namespace Parsisolution\Gateway\Providers\JiBit;

use Exception;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class JiBit extends AbstractProvider
{

    /**
     * Address of main CURL server
     *
     * @var string
     */
    const SERVER_URL = 'https://pg.jibit.mobi';

    const URL_AUTHENTICATE = '/authenticate';

    const URL_INITIATE = '/order/initiate';

    const URL_VERIFY = '/order/verify/';

    const URL_INQUIRY = '/order/inquiry/';

    /**
     * Address of main CURL server
     * Set after sendPayRequest
     *
     * @var string
     */
    protected $gateUrl;

    /**
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
    {
        return GatewayManager::JIBIT;
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
        $token = $this->getToken();

        $fields = array(
            'amount'          => $transaction->getAmount()->getRiyal(),
            'callBackUrl'     => $this->getCallback($transaction),
            'userIdentity'    => $this->config['user-mobile'],
            'merchantOrderId' => $this->config['merchant-id'],
            'additionalData'  => $transaction->getExtraField('additional'),
            'description'     => $transaction->getExtraField('description'),
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::SERVER_URL.self::URL_INITIATE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer '.$token,
            'Content-Type: application/json',
        ));

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($response['errorCode']) && $response['errorCode'] === 0) {
            $this->gateUrl = $response['result']['redirectUrl'];
            $refId = $response['result']['orderId'];

            return AuthorizedTransaction::make($transaction, $refId);
        }

        throw new JiBitException(@$response['errorCode'], @$response['message']);
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return RedirectResponse
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        return new RedirectResponse(RedirectResponse::TYPE_GET, $this->gateUrl);
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
        $status = $request->input('status');

        if ($status == 'PURCHASE_BY_USER') {
            return true;
        }

        throw new JiBitException($status);
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
        $token = $this->getToken();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::SERVER_URL.self::URL_VERIFY.$transaction->getReferenceId());
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer '.$token,
            'Content-Type: application/json',
        ));

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($response['errorCode']) && $response['errorCode'] == 0) {
            return new SettledTransaction($transaction, $transaction->getReferenceId());
        }

        throw new JiBitException(@$response['message'], @$response['errorCode']);
    }

    /**
     * Inquiry the transaction's status and return its response
     *
     * @param AuthorizedTransaction $transaction
     * @return array
     * @throws TransactionException
     * @throws Exception
     */
    protected function inquiryTransaction(AuthorizedTransaction $transaction)
    {
        $token = $this->getToken();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::SERVER_URL.self::URL_INQUIRY.$transaction->getReferenceId());
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer '.$token,
            'Content-Type: application/json',
        ));

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return $response;
    }

    /**
     * Get a token from server
     *
     * @return string
     *
     * @throws JiBitException
     */
    protected function getToken()
    {
        $fields = array(
            'username' => $this->config['merchant-id'],
            'password' => $this->config['password'],
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::SERVER_URL.self::URL_AUTHENTICATE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($response['errorCode']) && $response['errorCode'] === 0) {
            $token = $response['result']['token'];

            return $token;
        }

        throw new JiBitException(@$response['errorCode'], @$response['message']);
    }
}
