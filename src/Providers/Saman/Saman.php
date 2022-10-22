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
        ];

        $fields = array_merge($fields, $transaction->getExtraField('optional_data', []));

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
        $refId = $request->input('RefNum');

        $soap = new SoapClient(self::SERVER_VERIFY_URL, $this->soapConfig());
        $response = $soap->VerifyTransaction($refId, $this->config['terminal-id']);

        $response = intval($response);

        if ($response == $transaction->getAmount()->getRiyal()) {
//            $toMatch = new FieldsToMatch(null, null, null, new Amount($response, 'IRR'));
            $toMatch = new FieldsToMatch();

            $trace_number = $request->input('TraceNo');
            $hashed_card_number = $request->input('HashedCardNumber');
            $affective_amount = $request->input('AffectiveAmount');
            $wage = $request->input('Wage');

            return new SettledTransaction(
                $transaction,
                $refId,
                $toMatch,
                $request->input('SecurePan'),
                $request->input('Rrn'),
                compact('trace_number', 'hashed_card_number', 'affective_amount', 'wage'),
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

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'        => '09124441122',
            'optional_data' => [
                'ResNum1' => 'دیتای اضافی که توسط سایت پذیرنده ارسال و '.
                    'فقط هنگام گزارشگیری در پنل گزارش تراکنش قابل دسترسی می باشد. (حداکثر ۵۰ کاراکتر)',
                'ResNum2' => 'دیتای اضافی که توسط سایت پذیرنده ارسال و '.
                    'فقط هنگام گزارشگیری در پنل گزارش تراکنش قابل دسترسی می باشد. (حداکثر ۵۰ کاراکتر)',
                'ResNum3' => 'دیتای اضافی که توسط سایت پذیرنده ارسال و '.
                    'فقط هنگام گزارشگیری در پنل گزارش تراکنش قابل دسترسی می باشد. (حداکثر ۵۰ کاراکتر)',
                'ResNum4' => 'دیتای اضافی که توسط سایت پذیرنده ارسال و '.
                    'فقط هنگام گزارشگیری در پنل گزارش تراکنش قابل دسترسی می باشد. (حداکثر ۵۰ کاراکتر)',
            ],
        ];
    }
}
