<?php

namespace Parsisolution\Gateway\Providers\Parsian;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
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
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $params = [
            'LoginAccount'   => $this->config['login-account'],
            'Amount'         => $transaction->getAmount()->getRiyal(),
            'OrderId'        => $transaction->getOrderId(),
            'CallBackUrl'    => $this->getCallback($transaction),
            'AdditionalData' => $transaction->getExtraField('description'),
        ];
        $mobile = $transaction->getExtraField('mobile');
        if (!empty($mobile)) {
            $params['Originator'] = '98'.substr($mobile, 1);
        }

        $soap = new SoapClient(self::SERVER_URL, $this->soapConfig());
        $response = $soap->SalePaymentRequest(['requestData' => $params]);

        if ($response->SalePaymentRequestResult->Status === 0) {
            $url = self::URL_GATE.$response->SalePaymentRequestResult->Token;

            $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, $url, []);

            return AuthorizedTransaction::make(
                $transaction,
                null,
                $response->SalePaymentRequestResult->Token,
                $redirectResponse
            );
        }

        throw new ParsianException(
            $response->SalePaymentRequestResult->Status,
            $response->SalePaymentRequestResult->Message
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $status = $request->input('status');

        if ($status != 0) {
            throw new ParsianException($status);
        }

        $orderId = $request->input('OrderId');
        $token = $request->input('Token');
        $amount = $request->input('Amount');

        return new FieldsToMatch($orderId, null, $token, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $traceNumber = $request->input('RRN');

        $params = [
            'LoginAccount' => $this->config['login-account'],
            'Token'        => $transaction->getToken(),
        ];

        $soap = new SoapClient(self::SERVER_VERIFY_URL, $this->soapConfig());
        $result = $soap->ConfirmPayment(['requestData' => $params]);

        if ($result === false || ! isset($result->ConfirmPaymentResult->Status)) {
            throw new ParsianException(-1);
        }

        if ($result->ConfirmPaymentResult->Status !== 0) {
            throw new ParsianException($result->ConfirmPaymentResult->Status);
        }

        $toMatch = new FieldsToMatch(null, null, $result->ConfirmPaymentResult->Token);

        $cardNumber = $result->ConfirmPaymentResult->CardNumberMasked;

        return new SettledTransaction($transaction, $traceNumber, $toMatch, $cardNumber);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'      => '09124441122',
            'description' => 'رشته با طول حداکثر ۵۰۰ کاراکتر حاوی داده‌های اضافی است'.
                ' که پذیرنده می‌تواند به منظور بهره برداری‌های بعدی آن را به درگاه پرداخت ارسال نماید',
        ];
    }
}
