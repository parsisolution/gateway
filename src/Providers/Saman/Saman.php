<?php

namespace Parsisolution\Gateway\Providers\Saman;

use Exception;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Saman extends AbstractProvider
{

    /**
     * Address of main SOAP server
     *
     * @var string
     */
    const SERVER_URL = 'https://sep.shaparak.ir/payments/referencepayment.asmx?wsdl';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://sep.shaparak.ir/Payment.aspx';

    /**
     *
     * @var array $optional_data An array of optional data
     *  that will be sent with the payment request
     *
     */
    protected $optional_data = [];

    /**
     *
     * Add optional data to the request
     *
     * @param array $data an array of data
     *
     */
    public function setOptionalData(Array $data)
    {
        $this->optional_data = $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::SAMAN;
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
        $main_data = [
            'Amount'      => $transaction->getAmount()->getRiyal(),
            'MID'         => $this->config['merchant'],
            'ResNum'      => $transaction->getOrderId(),
            'RedirectURL' => $this->getCallback($transaction->generateUnAuthorized()),
        ];

        $data = array_merge($main_data, $this->optional_data);

        return new RedirectResponse(RedirectResponse::TYPE_POST, self::GATE_URL, $data);
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
        $payRequestRes = $request->input('State');
        $payRequestResCode = $request->input('StateCode');

        if ($payRequestRes == 'OK') {
            return true;
        }

        throw new SamanException($payRequestRes);
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
        $refId = $request->input('RefNum');
        $traceNumber = $request->input('TRACENO');
        $cardNumber = $request->input('SecurePan');
        $cardId = $request->input('CID');
        $RRN = $request->input('RRN');

        $fields = [
            "merchantID" => $this->config['merchant'],
            "password"   => $this->config['password'],
            "RefNum"     => $refId,
        ];

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->VerifyTransaction($fields["RefNum"], $fields["merchantID"]);

        $response = intval($response);

        if ($response == $transaction->getAmount()->getRiyal()) {
            return new SettledTransaction($transaction, $traceNumber, $cardNumber, $RRN, compact('cardId'), $refId);
        }

        //Reverse Transaction
        if ($response > 0) {
            $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
            $response = $soap->ReverseTransaction(
                $fields["RefNum"],
                $fields["merchantID"],
                $fields["password"],
                $response
            );
        }

        throw new SamanException($response);
    }
}
