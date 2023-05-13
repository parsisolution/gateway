<?php

namespace Parsisolution\Gateway\Providers\IDPay;

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

class IDPay extends AbstractProvider implements ProviderInterface
{
    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://api.idpay.ir/v1.1/payment';

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'order_id' => $transaction->getOrderId(),
            'amount'   => $transaction->getAmount()->getRiyal(),
            'name'     => $transaction->getExtraField('name'),
            'phone'    => $transaction->getExtraField('mobile'),
            'mail'     => $transaction->getExtraField('email'),
            'desc'     => $transaction->getExtraField('description'),
            'callback' => $this->getCallback($transaction),
        ];

        [$result, $http_code] = Curl::execute(self::SERVER_URL, $fields, true, [
            CURLOPT_HTTPHEADER => $this->generateHeaders(),
        ]);

        if ($http_code != 201 || empty($result) || empty($result['id']) || empty($result['link'])) {
            throw new IDPayException($result['error_code'] ?? $http_code, $result['error_message'] ?? null);
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $result['link']);

        return AuthorizedTransaction::make($transaction, $result['id'], null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('status')) {
            throw new InvalidRequestException();
        }

        $status = $request->input('status');
        if ($status != 10) {
            throw new IDPayException($status, $this->getStatusMessage($status));
        }

        $orderId = $request->input('order_id');
        $referenceId = $request->input('id');
        $amount = $request->input('amount');

        return new FieldsToMatch($orderId, $referenceId, null, new Amount($amount, 'IRR'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        //        $request->input('status');
        //        $request->input('track_id');
        //        $request->input('id');
        //        $request->input('order_id');
        //        $request->input('amount');
        //        $request->input('card_no');
        //        $request->input('hashed_card_no');
        //        $request->input('date');

        $fields = [
            'id'       => $transaction->getReferenceId(),
            'order_id' => $transaction->getOrderId(),
        ];

        [$result, $http_code] = Curl::execute(self::SERVER_URL.'/verify', $fields, true, [
            CURLOPT_HTTPHEADER => $this->generateHeaders(),
        ]);

        if ($http_code != 200) {
            throw new IDPayException($result['error_code'] ?? $http_code, $result['error_message'] ?? null);
        }

        if (empty($result['status']) || empty($result['track_id']) || $result['status'] < 100) {
            throw new IDPayException($result['status'], $this->getStatusMessage($result['status']));
        }

        $toMatch = new FieldsToMatch($result['order_id'], $result['id'], null, new Amount($result['amount'], 'IRR'));

        return new SettledTransaction(
            $transaction,
            $result['track_id'],
            $toMatch,
            $result['payment']['card_no'] ?? '',
            '',
            $result['payment'] ?? []
        );
    }

    /**
     * @return string[]
     */
    protected function generateHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'X-API-KEY: '.$this->config['api-key'],
            'X-SANDBOX: '.($this->config['sandbox'] ? 'true' : 'false'),
        ];
    }

    protected function getStatusMessage(int $code): ?string
    {
        $status_codes = [
            1   => 'پرداخت انجام نشده است',
            2   => 'پرداخت ناموفق بوده است',
            3   => 'خطا رخ داده است',
            4   => 'بلوکه شده',
            5   => 'برگشت به پرداخت کننده',
            6   => 'برگشت خورده سیستمی',
            7   => 'انصراف از پرداخت',
            8   => 'به درگاه پرداخت منتقل شد',
            10  => 'در انتظار تایید پرداخت',
            100 => 'پرداخت تایید شده است',
            101 => 'پرداخت قبلا تایید شده است',
            200 => 'به دریافت کننده واریز شد',
        ];

        return $status_codes[$code] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'      => '09124441122',
            'name'        => 'نام پرداخت کننده به طول حداکثر 255 کاراکتر',
            'email'       => 'پست الکترونیک پرداخت کننده به طول حداکثر 255 کاراکتر',
            'description' => 'توضیح تراکنش به طول حداکثر 255 کاراکتر',
        ];
    }
}
