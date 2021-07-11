<?php

namespace Parsisolution\Gateway\Providers\Irankish;

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

class Irankish extends AbstractProvider
{

    /**
     * Address of main server
     *
     * @var string
     */
    const SERVER_URL = 'https://ikc.shaparak.ir/XToken/Tokens.xml';

    /**
     * Address of SOAP server for verify payment
     *
     * @var string
     */
    const SERVER_VERIFY_URL = 'https://ikc.shaparak.ir/XVerify/Verify.xml';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://ikc.shaparak.ir/TPayment/Payment/index';

    /**
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
    {
        return GatewayManager::IRANKISH;
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
        $fields = array(
            'amount'           => $transaction->getAmount()->getRiyal(),
            'merchantId'       => $this->config['merchant-id'],
            'description'      => $this->config['description'],
            'invoiceNo'        => $transaction->getId(),
            'paymentId'        => $transaction->getId(),
            'specialPaymentId' => $transaction->getId(),
            'revertURL'        => $this->getCallback($transaction),
        );

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig(), array('soap_version' => SOAP_1_1));
        $response = $soap->MakeToken($fields);

        if ($response->MakeTokenResult->result == false) {
            throw new IrankishException($response->MakeTokenResult->result, $response->MakeTokenResult->message);
        }

        return AuthorizedTransaction::make($transaction, $response->MakeTokenResult->token);
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return RedirectResponse
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        $data = [
            'merchantId' => $this->config['merchant-id'],
            'token' => $transaction->getReferenceId(),
        ];

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
//        $refId = $request->input('token');
        $resultCode = $request->input('resultCode');

        if ($resultCode == '100') {
            return true;
        }

        throw new IrankishException($resultCode);
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
        $trackingCode = $request->input('referenceId');

        $fields = array(
            'token'           => $transaction->getReferenceId(),
            'referenceNumber' => $trackingCode,
            'merchantId'      => $this->config['merchant-id'],
            'sha1Key'         => $this->config['sha1-key'],
        );

        $soap = new SoapClient(self::SERVER_VERIFY_URL, $this->soapConfig());
        $response = $soap->KicccPaymentsVerification($fields);

        $response = floatval($response->KicccPaymentsVerificationResult);

        if ($response > 0) {
            return new SettledTransaction($transaction, $trackingCode);
        }

        throw new IrankishException($response);
    }
}
