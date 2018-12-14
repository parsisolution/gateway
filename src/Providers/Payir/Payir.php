<?php

namespace Parsisolution\Gateway\Providers\Payir;

use Exception;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Payir extends AbstractProvider
{

    /**
     * Address of main CURL server
     *
     * @var string
     */
    const SERVER_URL = 'https://pay.ir/payment/send';

    /**
     * Address of CURL server for verify payment
     *
     * @var string
     */
    const SERVER_VERIFY_URL = 'https://pay.ir/payment/verify';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const URL_GATE = 'https://pay.ir/payment/gateway/';

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
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
    {
        return GatewayManager::PAYIR;
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
            'api'      => $this->config['api'],
            'amount'   => $transaction->getAmount()->getToman(),
            'redirect' => $this->getCallback($transaction, true),
        ];

        $fields['factorNumber'] =
            isset($this->factorNumber) ? $this->factorNumber : $transaction->getExtraField('factor.number');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::SERVER_URL);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $response = json_decode($response, true);
        curl_close($ch);

        if (is_numeric($response['status']) && $response['status'] > 0) {
            return AuthorizedTransaction::make($transaction, $response['transId']);
        }

        throw new PayirSendException($response['errorCode']);
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
        $status = $request->input('status');
        $message = $request->input('message');

        if (is_numeric($status) && $status > 0) {
            return true;
        }

        throw new PayirReceiveException(-5, $message);
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
        $trackingCode = $request->input('transId');
        $cardNumber = $request->input('cardNumber');

        $fields = [
            'api'     => $this->config['api'],
            'transId' => $transaction->getReferenceId(),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::SERVER_VERIFY_URL);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $response = json_decode($response, true);
        curl_close($ch);

        if ($response['status'] == 1) {
            return new SettledTransaction($transaction, $trackingCode, $cardNumber);
        }

        throw new PayirReceiveException($response['errorCode']);
    }
}
