<?php

namespace Parsisolution\Gateway\Providers\TiPoul;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class TiPoul extends AbstractProvider implements ProviderInterface
{
    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://api.tipoul.com/pay/v1/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL_FOR_GET = 'https://api.tipoul.com/pay/v2/start?TokenHashed=';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL_FOR_POST = 'https://api.tipoul.com/pay/v1/start';

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'Amount'              => $transaction->getAmount()->getRiyal(),
            'CallBackUrl'         => $this->getCallback($transaction),
            'FactorNumber'        => $transaction->getOrderId(),
            'PayerUserId'         => $transaction->getExtraField('user_id'),
            'PayerName'           => $transaction->getExtraField('name'),
            'PayerMobile'         => $transaction->getExtraField('mobile'),
            'PayerMail'           => $transaction->getExtraField('email'),
            'ValidCardNum'        => $transaction->getExtraField('allowed_card'),
            'BlankForPayer'       => $transaction->getExtraField('blank_for_payer'),
            'BlankForTransaction' => $transaction->getExtraField('blank_for_transaction'),
            'Description'         => $transaction->getExtraField('description'),
            'IPG'                 => $transaction->getExtraField('ipg'),
        ];

        $result = $this->callApi('gettoken', $fields);

        $token = $result['accessToken'];
        $tokenHashed = $result['accessTokenHashed'];
        $traceNumber = $result['tipoulTraceNumber'];
        $token_track_number = $result['tipoulTrackNumber']; // request specific (not unique in transaction lifetime)

        if (Arr::get($this->config, 'redirect-method', RedirectResponse::TYPE_GET) == RedirectResponse::TYPE_GET) {
            $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, self::GATE_URL_FOR_GET.$tokenHashed);
        } else {
            $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_POST, self::GATE_URL_FOR_POST, [
                'TipoulAccessToken' => $token,
            ]);
        }

        $transaction['extra'] = $transaction->getExtra() + compact('token_track_number');

        return AuthorizedTransaction::make($transaction, $traceNumber, $tokenHashed, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('RespCode')) {
            throw new InvalidRequestException();
        }

        $code = $request->input('RespCode');
        if ($code != 0) {
            throw new TiPoulException($code, $request->input('RespMsg'));
        }

        $amount = $request->input('Amount');
        $factorNumber = $request->input('FactorNumber');
        $tipoulTraceNumber = $request->input('TipoulTraceNumber');

        return new FieldsToMatch($factorNumber, $tipoulTraceNumber, null, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $card_number = $request->input('cardNumber');
        $issuer_bank = $request->input('IssuerBank');
        $rrn = $request->input('RRN');
        $date_paid = $request->input('DatePaid');
        $trace_number = $request->input('TraceNumber');
        $callback_track_number = $request->input('TipoulTrackNumber');

        $result = $this->callApi('confirm', ['TipoulTraceNumber' => $transaction->getReferenceId()]);

        $confirm_track_number = $result['tipoulTrackNumber'] ?? null;

        return new SettledTransaction(
            $transaction,
            $transaction->getReferenceId(),
            new FieldsToMatch(),
            $card_number,
            $rrn,
            ['verify_result' => $result] +
            compact('issuer_bank', 'date_paid', 'trace_number', 'callback_track_number', 'confirm_track_number')
        );
    }

    /**
     * @return mixed
     *
     * @throws TiPoulException
     */
    protected function callApi(string $path, array $fields)
    {
        $fields['GateToken'] = $this->config['token'];
        [$response, $http_code, $error] = Curl::execute(self::SERVER_URL.$path, $fields);

        if ($http_code != 200 || ! isset($response['code']) || $response['code'] != 0) {
            throw new TiPoulException(
                $response['code'] ?? $http_code,
                $response['message'] ?? $error ?? null
            );
        }

        return $response['result'];
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'                => '09124441122',
            'name'                  => 'نام پرداخت کننده',
            'email'                 => 'test@gmail.com',
            'user_id'               => 'نام کاربری پرداخت کننده',
            'allowed_card'          => '(string[] list) شماره کارت های مجاز جهت انجام تراکنش',
            'blank_for_payer'       => 'فیلد گزارشی برای پرداخت کننده',
            'blank_for_transaction' => 'فیلد گزارشی برای تراکنش',
            'description'           => 'توضیحات تراکنش',
            'ipg'                   => 'IRK (ایران کیش) || '.
                'Sepehr (سپهر)'.
                ' - تیپول به صورت پیشفرض تراکنش های درخواستی را با استفاده از سیستم سویچینگ و'.
                ' هدایت هوشمند تراکنش های خود به بهترین، مناسب ترین و در دسترس ترین درگاه شاپرک یا PSP ارسال میکند،'.
                ' لیکن چنانچه در نظر دارید تراکنش های شما از PSP مشخص یا خاصی انجام شود،'.
                ' این فیلد را با کلید مربوط به PSP به صورت زیر ارسال نمایید',
        ];
    }
}
