<?php

namespace Parsisolution\Gateway\Providers\Payir;

use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Payir extends AbstractProvider
{

    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://pay.ir/pg/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const URL_GATE = 'https://pay.ir/pg/';

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::PAYIR;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'amount'          => $transaction->getAmount()->getRiyal(),
            'redirect'        => $this->getCallback($transaction, true),
            'mobile'          => $transaction->getExtraField('mobile'),
            'factorNumber'    => $transaction->getOrderId(),
            'description'     => $transaction->getExtraField('description'),
            'validCardNumber' => $transaction->getExtraField('allowed_card'),
        ];

        $result = $this->callApi('send', $fields);

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, self::URL_GATE . $result['token']);

        return AuthorizedTransaction::make($transaction, null, $result['token'], $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (!$request->has('status')) {
            throw new InvalidRequestException();
        }

        $status = $request->input('status');
        if ($status != 1) {
            throw new PayirException($status ?: -5);
        }

        $token = $request->input('token');

        return new FieldsToMatch(null, null, $token);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $result = $this->callApi('verify', ['token' => $transaction->getToken()]);

        $toMatch = new FieldsToMatch($result['factorNumber'], null, null, new Amount($result['amount'], 'IRR'));

        return new SettledTransaction(
            $transaction,
            $result['transId'],
            $toMatch,
            $result['cardNumber'],
            '',
            ['verify_result' => $result]
        );
    }

    /**
     * @param string $path
     * @param array $fields
     * @return mixed
     * @throws PayirException
     */
    protected function callApi(string $path, array $fields)
    {
        $fields['api'] = $this->config['api-key'];
        list($response, $http_code, $error) = Curl::execute(self::SERVER_URL . $path, $fields, true, [
            CURLOPT_SSL_VERIFYPEER => false,
        ], Curl::METHOD_GET);

        if ($http_code != 200 || empty($response['status']) || $response['status'] != 1) {
            throw new PayirException(
                $response['errorCode'] ?? $http_code,
                $response['errorMessage'] ?? $error ?? null
            );
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'        => '09124441122',
            'factor_number' => 'شماره فاکتور شما ( اختیاری )',
            'description'   => 'توضیحات تراکنش ( اختیاری ، حداکثر 255 کاراکتر )',
            'allowed_card'  => 'اعلام شماره کارت مجاز برای انجام تراکنش' .
                ' ( اختیاری، بصورت عددی (لاتین) و چسبیده بهم در 16 رقم. مثال 6219861012345678 )',
        ];
    }
}
