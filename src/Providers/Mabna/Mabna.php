<?php
/**
 * Created by PhpStorm.
 * User: Ali Ghasemzadeh
 * Date: 11/29/2018
 * Time: 10:39 PM
 */

namespace Parsisolution\Gateway\Providers\Mabna;

use Exception;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Mabna extends AbstractProvider
{

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const URL_GATE = 'https://mabna.shaparak.ir:8080/Pay/';

    /**
     * Address of CURL server for verify payment
     *
     * @var string
     */
    const SERVER_VERIFY_URL = 'https://mabna.shaparak.ir:8081/V1/PeymentApi/Advice';

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::MABNA;
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
        return AuthorizedTransaction::make($transaction);
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return RedirectResponse
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        $callback = $this->getCallback($transaction->generateUnAuthorized());
        $terminalId = $this->config['terminalId'];

        $data = [
            'TerminalID' => $terminalId,
            'Amount' => $transaction->getAmount()->getRiyal(),
            'callbackURL' => $callback,
            'InvoiceID' => $transaction->getOrderId(),
        ];

        return new RedirectResponse(RedirectResponse::TYPE_POST, self::URL_GATE, $data);
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
        $status = $request->input('respcode');
        if ($status == 0) {
            return true;
        }

        throw new MabnaException(-7);
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
        $traceNumber = $request->input('tracenumber');
        $cardNumber = $request->input('cardnumber');
        $rrn = $request->input('rrn');
        $digitalreceipt = $request->input('digitalreceipt');

        $data = [
            "digitalreceipt" => $digitalreceipt,
            "Tid"            => $this->config['terminalId'],
        ];
        $data = http_build_query($data);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::SERVER_VERIFY_URL);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($response, true);
        if ($result["Status"] == "Ok") {
            return new SettledTransaction($transaction, $traceNumber, $cardNumber, $rrn, [
                'digital_receipt' => $digitalreceipt,
            ]);
        }

        throw new MabnaException($response['ReturnId']);
    }
}
