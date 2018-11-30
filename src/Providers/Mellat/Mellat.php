<?php

namespace Parsisolution\Gateway\Providers\Mellat;

use DateTime;
use Exception;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Mellat extends AbstractProvider
{

    /**
     * Address of main SOAP server
     *
     * @var string
     */
    const SERVER_URL = 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl';

    /**
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
    {
        return GatewayManager::MELLAT;
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
        $dateTime = new DateTime();

        $fields = array(
            'terminalId'     => $this->config['terminalId'],
            'userName'       => $this->config['username'],
            'userPassword'   => $this->config['password'],
            'orderId'        => $transaction->getId(),
            'amount'         => $transaction->getAmount()->getRiyal(),
            'localDate'      => $dateTime->format('Ymd'),
            'localTime'      => $dateTime->format('His'),
            'additionalData' => $transaction->getExtraField('description'),
            'callBackUrl'    => $this->getCallback($transaction),
            'payerId'        => $transaction->getExtraField('payer.id', 0),
        );

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->bpPayRequest($fields);

        $response = explode(',', $response->return);

        if ($response[0] != '0') {
            throw new MellatException($response[0]);
        }

        return AuthorizedTransaction::make($transaction, $response[1]);
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        $refId = $transaction->getReferenceId();

        return $this->view('gateway::mellat-redirector')->with(compact('refId'));
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
        $payRequestResCode = $request->input('ResCode');

        if ($payRequestResCode == '0') {
            return true;
        }

        throw new MellatException($payRequestResCode);
    }

    /**
     * Verify user payment from bank server
     *
     * @param SettledTransaction $transaction
     * @return bool
     *
     * @throws MellatException
     * @throws \SoapFault
     */
    protected function verifyPayment(SettledTransaction $transaction)
    {
        $fields = [
            'terminalId'      => $this->config['terminalId'],
            'userName'        => $this->config['username'],
            'userPassword'    => $this->config['password'],
            'orderId'         => $transaction->getId(),
            'saleOrderId'     => $transaction->getId(),
            'saleReferenceId' => $transaction->getTrackingCode(),
        ];

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->bpVerifyRequest($fields);

        if ($response->return != '0') {
            throw new MellatException($response->return);
        }

        return true;
    }

    /**
     * Send settle request
     *
     * @param SettledTransaction $transaction
     * @return bool
     *
     * @throws MellatException
     * @throws \SoapFault
     */
    protected function settleRequest(SettledTransaction $transaction)
    {
        $fields = [
            'terminalId'      => $this->config['terminalId'],
            'userName'        => $this->config['username'],
            'userPassword'    => $this->config['password'],
            'orderId'         => $transaction->getId(),
            'saleOrderId'     => $transaction->getId(),
            'saleReferenceId' => $transaction->getTrackingCode(),
        ];

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->bpSettleRequest($fields);

        if ($response->return == '0' || $response->return == '45') {
            return true;
        }

        throw new MellatException($response->return);
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
        $trackingCode = $request->input('SaleReferenceId');
        $cardNumber = $request->input('CardHolderPan');
        $settledTransaction = new SettledTransaction($transaction, $trackingCode, $cardNumber);

        $this->verifyPayment($settledTransaction);
        $this->settleRequest($settledTransaction);

        return $settledTransaction;
    }
}
