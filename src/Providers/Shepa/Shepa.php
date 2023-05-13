<?php

namespace Parsisolution\Gateway\Providers\Shepa;

use Illuminate\Container\Container;
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

class Shepa extends AbstractProvider implements ProviderInterface
{
    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://merchant.shepa.com/api/v1/';

    /**
     * Address of sandbox server
     *
     * @var string
     */
    const SERVER_SANDBOX_URL = 'https://sandbox.shepa.com/api/v1/';

    /**
     * Address of main server
     *
     * @var string
     */
    protected $serverUrl;

    public function __construct(Container $app, $id, $config)
    {
        parent::__construct($app, $id, $config);

        $this->setServer();
    }

    /**
     * Set server for soap transfers data
     *
     * @return void
     */
    protected function setServer()
    {
        if (Arr::get($this->config, 'sandbox', false)) {
            $this->serverUrl = self::SERVER_SANDBOX_URL;
        } else {
            $this->serverUrl = self::SERVER_URL;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'api'         => $this->config['api-key'],
            'amount'      => $transaction->getAmount()->getTotal(),
            'callback'    => $this->getCallback($transaction),
            'mobile'      => $transaction->getExtraField('mobile'),
            'email'       => $transaction->getExtraField('email'),
            'description' => $transaction->getExtraField('description'),
        ];

        $result = $this->callApi('token', $fields);

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $result['url']);

        return AuthorizedTransaction::make($transaction, null, $result['token'], $redirectResponse);
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
        if ($status != 'success') {
            throw new ShepaException($status);
        }

        return new FieldsToMatch(null, null, $request->input('token'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'api'    => $this->config['api-key'],
            'amount' => $transaction->getAmount()->getTotal(),
            'token'  => $transaction->getToken(),
        ];

        $result = $this->callApi('verify', $fields);

        $traceNumber = $result['refid'];
        $transactionId = $result['transaction_id'];
        $date = $result['date'];
        $amount = $result['amount'];
        $cardNumber = $result['card_pan'];

        $toMatch = new FieldsToMatch(null, null, null, new Amount($amount, $transaction->getAmount()->getCurrency()));

        return new SettledTransaction(
            $transaction,
            $traceNumber,
            $toMatch,
            $cardNumber,
            '',
            compact('date'),
            $transactionId
        );
    }

    /**
     * @return mixed
     *
     * @throws ShepaException
     */
    protected function callApi(string $path, array $fields)
    {
        [$response, $http_code, $error] = Curl::execute($this->serverUrl.$path, $fields);

        if ($http_code != 200 || empty($response['success']) || ! $response['success']) {
            throw new ShepaException(
                $response['errorCode'] ?? $http_code,
                implode('; ', $response['error']) ?? $error ?? null
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
            'mobile'      => '09124441122',
            'email'       => 'test@gmail.com',
            'description' => 'توضیحات مربوط به تراکش',
        ];
    }
}
