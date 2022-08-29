<?php

namespace Parsisolution\Gateway\Providers\Vandar;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Vandar extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://ipg.vandar.io/api/v3';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://ipg.vandar.io/v3/';


    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::VANDAR;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'api_key'           => $this->config['api-key'],
            'amount'            => $transaction->getAmount()->getRiyal(),
            'callback_url'      => $this->getCallback($transaction),
            'mobile_number'     => $transaction->getExtraField('mobile'),
            'factorNumber'      => $transaction->getOrderId(),
            'description'       => $transaction->getExtraField('description'),
            'national_code'     => $transaction->getExtraField('national_code'),
            'valid_card_number' => $transaction->getExtraField('valid_card_number'),
            'comment'           => $transaction->getExtraField('comment'),
        ];

        list($result, $http_code) = Curl::execute(self::SERVER_URL.'/send', $fields);

        if ($http_code != 200 || empty($result['status']) || $result['status'] != 1) {
            throw new VandarException($http_code, implode('; ', $result['errors']) ?? null);
        }

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, self::GATE_URL.$result['token']);

        return AuthorizedTransaction::make($transaction, null, $result['token'], $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('payment_status')) {
            throw new InvalidRequestException();
        }

        $status = $request->input('payment_status');
        if ($status != 'OK') {
            throw new VandarException($status);
        }

        return new FieldsToMatch(null, null, $request->input('token'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'api_key' => $this->config['api-key'],
            'token'   => $transaction->getToken(),
        ];

        list($result, $http_code) = Curl::execute(self::SERVER_URL.'/verify', $fields);

        if ($http_code != 200 || empty($result['status'])) {
            if (intval($result['status']) > 1) {
                throw new VandarException(
                    $result['status'],
                    empty($result['errors']) ?
                        $this->getStatusMessage($result['status']) :
                        implode('; ', $result['errors'])
                );
            }
            throw new VandarException($http_code, implode('; ', $result['errors']) ?? null);
        }


//        $amount = $result['amount'];
//        $realAmount = $result['realAmount'];
//        $wage = $result['wage'];
        $traceNumber = $result['transId'];
//        $factorNumber = $result['factorNumber'];
//        $mobile = $result['mobile'];
//        $description = $result['description'];
        $cardNumber = $result['cardNumber'];
//        $paymentDate = $result['paymentDate'];
        $cardId = $result['cid'];
        $message = $result['message'];

        $toMatch = new FieldsToMatch($result['factorNumber']);

        return new SettledTransaction(
            $transaction,
            $traceNumber,
            $toMatch,
            $cardNumber ?? '',
            '',
            compact('cardId', 'message', 'result')
        );
    }

    /**
     * @param int $code
     * @return string|null
     */
    protected function getStatusMessage(int $code): ?string
    {
        $status_codes = [
            0 => 'مقادیر ارسالی اشتباه است',
            1 => 'پرداخت تایید شده است',
            2 => 'پرداخت از قبل وریفای شده است',
            3 => 'زمان تایید تراکنش (حداکثر ۲۰ دقیقه بعد از ارسال تراکنش) منقضی شده است',
        ];

        return $status_codes[$code] ?? null;
    }
}
