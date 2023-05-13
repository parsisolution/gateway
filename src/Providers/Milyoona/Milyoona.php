<?php

namespace Parsisolution\Gateway\Providers\Milyoona;

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

class Milyoona extends AbstractProvider implements ProviderInterface
{
    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://api.milyoona.com/payment/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://api.milyoona.com/ipg/';

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'amount'        => $transaction->getAmount()->getToman(),
            'callback_url'  => $this->getCallback($transaction),
            'order_id'      => str_pad($transaction->getOrderId(), 16, '0'),
            'mobile'        => $transaction->getExtraField('mobile'),
            'national_code' => $transaction->getExtraField('national_code'),
            'card_no'       => $transaction->getExtraField('allowed_card'),
            'description'   => $transaction->getExtraField('description'),
        ];

        $result = $this->callApi('token', $fields);

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, self::GATE_URL.$result['token']);

        return AuthorizedTransaction::make($transaction, $result['request_id'], $result['token'], $redirectResponse);
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
        if ($status != 'OK') {
            throw new MilyoonaException($status);
        }

        return new FieldsToMatch(null, null, $request->input('token'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $result = $this->callApi('verify', ['token' => $transaction->getToken()]);

        $token = $result['token'];
        $request_id = $result['request_id'];
        $order_id = $result['order_id'];
        $amount = $result['amount'];
        $cardNumber = $result['card_number'];
        $fee = $result['fee'];
        $paid_at = $result['paid_at'];

        $toMatch = new FieldsToMatch($order_id, $request_id, $token, new Amount($amount));

        return new SettledTransaction(
            $transaction,
            $request_id,
            $toMatch,
            $cardNumber,
            '',
            ['verify_result' => $result] + compact('fee', 'paid_at')
        );
    }

    /**
     * @throws MilyoonaException
     */
    public function trace(string $token): array
    {
        return $this->callApi('trace', compact('token'));
    }

    /**
     * @return mixed
     *
     * @throws MilyoonaException
     */
    protected function callApi(string $path, array $fields)
    {
        $fields['terminal'] = $this->config['terminal-id'];
        [$response, $http_code] = Curl::execute(self::SERVER_URL.$path, $fields);

        if ($http_code != 200 || ! isset($response['status']) || ! in_array($response['status'], [0, 5])) {
            throw new MilyoonaException(
                $response['status'] ?? $http_code,
                ! empty($response['errors']) ? json_encode($response['errors'], JSON_UNESCAPED_UNICODE) : null
            );
        }

        return $response['data'];
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'        => '09124441122',
            'national_code' => '(اختیاری) در صورت ارسال کدملی پرداخت کننده،'.
                ' شماره کارت پرداخت کننده می‌بایست متعلق به کد ملی ارسالی باشد.',
            'allowed_card' => '(اختیاری) در صورت ارسال شماره کارت،'.
                ' کاربر تنها قادر به پرداخت وجه با آن شماره کارت خواهد بود.',
            'description' => '(اختیاری) توضیحات ارسالی در پنل کاربری میلیونا به شما نمایش داده خواهد شد.',
        ];
    }
}
