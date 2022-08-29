<?php

namespace Parsisolution\Gateway\Providers\Saman;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Saman extends AbstractProvider
{

    /**
     * Url of parsian gateway web service
     *
     * @var string
     */
    const SERVER_URL = 'https://sep.shaparak.ir/MobilePG/MobilePayment';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const URL_GATE = 'https://sep.shaparak.ir/OnlinePG/OnlinePG';

    /**
     * Address of SOAP server for verify payment
     *
     * @var string
     */
    const SERVER_VERIFY_URL = 'https://sep.shaparak.ir/Payments/ReferencePayment.asmx?WSDL';

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
    public function setOptionalData(array $data)
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
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'Action'      => 'Token',
            'TerminalId'  => $this->config['terminal-id'],
            'RedirectUrl' => $this->getCallback($transaction),
            'ResNum'      => $transaction->getOrderId(),
            'Amount'      => $transaction->getAmount()->getRiyal(),
            'CellNumber'  => $transaction->getExtraField('mobile'),
            //    'ResNum1'     => '',
            //    'ResNum2'     => '',
            //    'ResNum3'     => '',
            //    'ResNum4'     => '',
        ];

        $fields = array_merge($fields, $this->optional_data);

        list($result) = Curl::execute(self::SERVER_URL, $fields);

        if ($result['status'] != 1) {
            throw new SamanException($result['errorCode'], $result['errorDesc']);
        }

        $token = $result['token'];
        $data = [
            'Token'     => $token,
            'GetMethod' => '', /* true | false | empty string | null */
        ];

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, self::URL_GATE, $data);

        return AuthorizedTransaction::make($transaction, null, $token, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $state = $request->input('State');
        $status = $request->input('Status');

        if ($state != 'OK') {
            throw new SamanException($status);
        }

        $orderId = $request->input('ResNum');
        $token = $request->input('Token');
        $amount = $request->input('Amount');

        return new FieldsToMatch($orderId, null, $token, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $RRN = $request->input('RRN');
        $refId = $request->input('RefNum');
        $traceNumber = $request->input('TraceNo');
        $cardNumber = $request->input('SecurePan');
        $cardId = $request->input('CID');

        $soap = new SoapClient(self::SERVER_VERIFY_URL, $this->soapConfig());
        $response = $soap->VerifyTransaction($refId, $this->config['terminal-id']);

        $response = intval($response);

        if ($response == $transaction->getAmount()->getRiyal()) {
//            $toMatch = new FieldsToMatch(null, null, null, new Amount($response, 'IRR'));
            $toMatch = new FieldsToMatch();

            return new SettledTransaction(
                $transaction,
                $refId,
                $toMatch,
                $cardNumber,
                $RRN,
                compact('traceNumber', 'cardId'),
                $refId
            );
        }

        //Reverse Transaction
        if ($response > 0) {
            $response = $soap->ReverseTransaction(
                $refId,
                $this->config['terminal-id'],
                $this->config['username'],
                $this->config['password']
            );

            throw new SamanException($response, 'Invalid Amount');
        }

        throw new SamanException($response);
    }
}
