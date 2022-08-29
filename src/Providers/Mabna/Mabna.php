<?php
/**
 * Created by PhpStorm.
 * User: Ali Ghasemzadeh
 * Date: 11/29/2018
 * Time: 10:39 PM
 */

namespace Parsisolution\Gateway\Providers\Mabna;

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
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $data = [
            'TerminalID'  => $this->config['terminal-id'],
            'Amount'      => $transaction->getAmount()->getRiyal(),
            'callbackURL' => $this->getCallback($transaction),
            'InvoiceID'   => $transaction->getOrderId(),
        ];

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, self::URL_GATE, $data);

        return AuthorizedTransaction::make($transaction, null, null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $code = $request->input('respcode');
        $message = $request->input('respmsg');

        if ($code != 0) {
            throw new MabnaException($code, $message);
        }

        $amount = $request->input('amount');
        $orderId = $request->input('invoiceid');

        return new FieldsToMatch($orderId, null, null, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $traceNumber = $request->input('tracenumber');
        $cardNumber = $request->input('cardnumber');
//        $issuerBank = $request->input('issuerbank');
        $rrn = $request->input('rrn');
        $digitalreceipt = $request->input('digitalreceipt');
//        $payload = $request->input('payload');

        $fields = [
            "digitalreceipt" => $digitalreceipt,
            "Tid"            => $this->config['terminal-id'],
        ];

        list($result) = Curl::execute(self::SERVER_VERIFY_URL, $fields, true, [
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        if ($result["Status"] != "Ok") {
            throw new MabnaException($result['ReturnId']);
        }

        $toMatch = new FieldsToMatch();

        return new SettledTransaction(
            $transaction,
            $traceNumber,
            $toMatch,
            $cardNumber,
            $rrn,
            [
                'digital_receipt' => $digitalreceipt,
            ]
        );
    }
}
