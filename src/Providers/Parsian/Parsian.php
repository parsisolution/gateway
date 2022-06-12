<?php

namespace Parsisolution\Gateway\Providers\Parsian;

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

class Parsian extends AbstractProvider
{

    /**
     * Url of parsian gateway web service
     *
     * @var string
     */
    const SERVER_URL = 'https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?wsdl';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const URL_GATE = 'https://pec.shaparak.ir/NewIPG/?Token=';

    /**
     * Address of SOAP server for verify payment
     *
     * @var string
     */
    const SERVER_VERIFY_URL = 'https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx?wsdl';

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::PARSIAN;
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
        $params = [
            'LoginAccount' => $this->config['pin'],
            'Amount'       => intval($transaction->getAmount()->getRiyal()),
            'OrderId'      => intval($transaction->getOrderId()),
            'CallBackUrl'  => $this->getCallback($transaction),
        ];

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->SalePaymentRequest(array('requestData' => $params));

        if ($response->SalePaymentRequestResult->Status === 0) {
            return AuthorizedTransaction::make($transaction, null, $response->SalePaymentRequestResult->Token);
        }

        throw new ParsianErrorException(
            $response->SalePaymentRequestResult->Status,
            $response->SalePaymentRequestResult->Message
        );
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return RedirectResponse
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        $url = self::URL_GATE.$transaction->getToken();

        return new RedirectResponse(RedirectResponse::TYPE_POST, $url, []);
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
//        $refId = $request->input('Token');
        $payRequestResCode = $request->input('status');

        if ($payRequestResCode == 0) {
            return true;
        }

        throw new ParsianErrorException($payRequestResCode);
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
        $traceNumber = $request->input('RRN');

        $params = array(
            'LoginAccount' => $this->config['pin'],
            'Token'        => $transaction->getToken(),
        );

        $soap = new SoapClient(self::SERVER_VERIFY_URL, $this->soapConfig());
        $result = $soap->ConfirmPayment(array('requestData' => $params));

        if ($result === false || ! isset($result->ConfirmPaymentResult->Status)) {
            throw new ParsianErrorException(-1);
        }

        if ($result->ConfirmPaymentResult->Status !== 0) {
            throw new ParsianErrorException($result->ConfirmPaymentResult->Status);
        }

        $cardNumber = $result->ConfirmPaymentResult->CardNumberMasked;

        return new SettledTransaction($transaction, $traceNumber, $cardNumber);
    }
}
