<?php

namespace Parsisolution\Gateway\Providers\Saman;

use Exception;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
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
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
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
        return AuthorizedTransaction::make($transaction, $transaction->getId());
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        $main_data = [
            'amount'      => $transaction->getAmount()->getRiyal(),
            'merchant'    => $this->config['merchant'],
            'resNum'      => $transaction->getId(),
            'callBackUrl' => $this->getCallback($transaction->generateUnAuthorized()),
        ];

        $data = array_merge($main_data, $this->optional_data);

        return $this->view('gateway::saman-redirector')->with($data);
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
        $trackingCode = $request->input('TRACENO');
        $cardNumber = $request->input('SecurePan');

        $fields = [
            "merchantID" => $this->config['merchant'],
            "password"   => $this->config['password'],
            "RefNum"     => $refId,
        ];

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->VerifyTransaction($fields["RefNum"], $fields["merchantID"]);

        $response = intval($response);

        if ($response == $transaction->getAmount()->getRiyal()) {
            $this->transaction->updateReferenceId($refId);

            return new SettledTransaction($transaction, $trackingCode, $cardNumber);
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
