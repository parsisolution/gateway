<?php

namespace Parsisolution\Gateway\Providers\Jibimo;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Jibimo extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://api.jibimo.com/v2/ipg/';


    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::JIBIMO;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'amount'              => $transaction->getAmount()->getRiyal(),
            'return_url'          => $this->getCallback($transaction),
            'check_national_code' => $transaction->getExtraField('check_national_code', false),
        ];
        $mobile = $transaction->getExtraField('mobile');
        if (! empty($mobile)) {
            $fields['mobile_number'] = '+98'.substr($mobile, 1);
        }
        $allowedCards = $transaction->getExtraField('allowed_card');
        if (! empty($allowedCards)) {
            $fields['authorized_card_numbers'] = $allowedCards;
        }

        list($result, $http_code) = Curl::execute(self::SERVER_URL.'request', $fields, true, [
            CURLOPT_HTTPHEADER => $this->generateHeaders(),
        ]);

        if ($http_code != 200) {
            throw new JibimoException($http_code, print_r($result['errors'], true) ?: null);
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $result['link']);

        return AuthorizedTransaction::make($transaction, $result['trx'], null, $redirectResponse);
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
        if ($status != 1) {
            throw new JibimoException($request->input('state_code'));
        }

        return new FieldsToMatch(null, $request->input('trx'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'trx' => $transaction->getReferenceId(),
        ];

        list($result, $http_code) = Curl::execute(self::SERVER_URL.'verify', $fields, true, [
            CURLOPT_HTTPHEADER => $this->generateHeaders(),
        ]);

        if ($http_code != 200 || empty($result['status']) || $result['status'] != 1) {
            throw new JibimoException($result['state_code'] ?? $http_code);
        }

        $tracking_id = $result['tracking_id'];
        $amount = $result['amount'];
        $trace_number = $result['trace_number'];
        $card_number = $result['card_number'];

        $toMatch = new FieldsToMatch(null, $tracking_id, null, new Amount($amount, 'IRR'));

        return new SettledTransaction($transaction, $trace_number, $toMatch, $card_number, '', [
            'card_hash'  => $result['card_hash'],
            'card_owner' => $result['card_owner'],
            'date'       => $result['date'],
        ]);
    }

    /**
     * @param string $transactionId
     * @return array
     */
    public function inquiry(string $transactionId): array
    {
        return Curl::execute(self::SERVER_URL.'trx/'.$transactionId, [], true, [
            CURLOPT_HTTPHEADER => $this->generateHeaders(true),
        ], Curl::METHOD_GET);
    }

    /**
     * @param bool $isGetRequest
     * @return string[]
     */
    protected function generateHeaders(bool $isGetRequest = false): array
    {
        $headers = [
            'Accept: application/json',
            'X-API-KEY: '.$this->config['api-key'],
        ];

        if (! $isGetRequest) {
            $headers[] = 'Content-Type: application/json';
        }

        return $headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'              => '09124441122',
            'check_national_code' => '(bool) true || false (default is false)'.
                'در صورتی که نیاز دارید که کد ملی دارنده کارت با کد ملی مربوط به صاحب شماره موبایل مطابقت داده شود'.
                ' و در صورت عدم تطابق، تراکنش انجام نگردد مقدار این فیلد را true ارسال نمایید.'.
                ' در صورتی که این فیلد true ارسال شود، شماره موبایل هم اجباری خواهد شد.',
            'allowed_card'        => '(string[] list)'.
                'در صورتی که نیاز دارید تراکنش کاربر فقط با شماره کارت‌های مشخصی امکان پذیر باشد'.
                ' و در غیر این صورت تراکنش انجام نگردد، این شماره کارت‌ها به صورت آرایه‌ای از طریق این فیلد ارسال گردد',
        ];
    }
}
