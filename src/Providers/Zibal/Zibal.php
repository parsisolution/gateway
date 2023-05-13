<?php

namespace Parsisolution\Gateway\Providers\Zibal;

use Illuminate\Http\Request;
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

class Zibal extends AbstractProvider implements ProviderInterface
{
    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://gateway.zibal.ir/v1/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://gateway.zibal.ir/start/';

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'merchant'          => $this->config['merchant'],
            'amount'            => $transaction->getAmount()->getRiyal(),
            'callbackUrl'       => $this->getCallback($transaction),
            'description'       => $transaction->getExtraField('description'),
            'orderId'           => $transaction->getOrderId(),
            'mobile'            => $transaction->getExtraField('mobile'),
            'national_code'     => $transaction->getExtraField('national_code'),
            'allowedCards'      => $transaction->getExtraField('allowed_card'),
            'linkToPay'         => $transaction->getExtraField('link_to_pay'),
            'sms'               => $transaction->getExtraField('sms'),
            'percentMode'       => ($transaction->getExtraField('percent_mode') ? 1 : 0),
            'feeMode'           => $transaction->getExtraField('fee_mode'),
            'multiplexingInfos' => $transaction->getExtraField('multiplexing_infos'),
        ];

        [$response, $http_code] = Curl::execute(self::SERVER_URL.'request', $fields);

        if ($http_code != 200 || empty($response['result']) || $response['result'] != 100) {
            throw new ZibalException($response['result'] ?? $http_code, $response['message'] ?? null);
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, self::GATE_URL.$response['trackId']);
        $extra = $transaction->getExtra();
        if (! empty($response['payLink'])) {
            $extra['pay_link'] = $response['payLink'];
        }
        $transaction['extra'] = $extra;

        return AuthorizedTransaction::make($transaction, $response['trackId'], null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('success')) {
            throw new InvalidRequestException();
        }

        $success = $request->input('success');
        if ($success != 1) {
            throw new ZibalException($request->input('status'));
        }

        return new FieldsToMatch($request->input('orderId'), $request->input('trackId'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'merchant' => $this->config['merchant'],
            'trackId'  => $transaction->getReferenceId(),
        ];

        [$response, $http_code] = Curl::execute(self::SERVER_URL.'verify', $fields);

        if ($http_code != 200 || empty($response['result']) || ! in_array($response['result'], [100, 202])) {
            throw new ZibalException($response['result'] ?? $http_code, $response['message'] ?? null);
        }
        if ($response['result'] == 202) {
            throw new ZibalException($response['status'] ?? $response['result'] ?? $http_code);
        }

        $orderId = $response['orderId'];
        $amount = $response['amount'];
        $traceNumber = $response['refNumber'] ?? $transaction->getReferenceId();
        $cardNumber = $response['cardNumber'];
        $paid_at = $response['paidAt'];

        $toMatch = new FieldsToMatch($orderId, null, null, new Amount($amount, 'IRR'));

        return new SettledTransaction($transaction, $traceNumber, $toMatch, $cardNumber, '', compact('paid_at'));
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'       => '09124441122',
            'description'  => 'توضیحات مربوط به سفارش (در گزارشات مختلف نشان‌داده خواهند شد)',
            'allowed_card' => '(string[] list)'.
                ' چنانچه تمایل دارید کاربر فقط از شماره کارت‌های مشخصی بتواند پرداخت کند'.
                ' لیست کارت(های) 16 رقمی را ارسال کنید',
            'link_to_pay' => '(bool) true || false'.
                ' در صورتی که درگاه شما دسترسی ارسال لینک کوتاه پرداخت را داشته باشد،'.
                ' با قراردادن این متغیر برابر با true لینک کوتاه پرداخت برای این تراکنش ساخته می‌شود.'.
                ' لازم به ذکر است در این حالت callbackUrl میتواند ارسال نشود',
            'sms' => '(bool) true || false '.
                ' با قراردادن این متغیر برابر با true لینک کوتاه پرداخت به شماره mobile ارسال خواهد شد',
            'percent_mode' => '(bool) true || false (default is false)'.
                ' در صورتی که نحوه تسهیم مبلغ شما به‌صورت درصدی می‌باشد، این مقدار را true ارسال کنید',
            'fee_mode' => '(integer) 0 (کسر از تراکنش) ||'.
                ' 1 (کسر کارمزد از کیف پول متصل به درگاه (در پرداختیاری پشتیبانی نمی شود)) ||'.
                ' 2 (افزوده شدن مبلغ کارمزد به مبلغ پرداختی توسط مشتری)',
            'multiplexing_infos' => 'لیستی از شی آیتم تسهیم - برای اطلاعات بیشتر مستندات درگاه مطالعه شود',
        ];
    }
}
