<?php

namespace Parsisolution\Gateway\Providers\YekPay;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Curl;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\Amount;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class YekPay extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://gate.yekpay.com/api/payment';

    /**
     * Address of sandbox server
     *
     * @var string
     */
    const SERVER_SANDBOX_URL = 'https://api.yekpay.com/api/sandbox';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://gate.yekpay.com/api/payment/start/';

    /**
     * Address of sandbox gate for redirect
     *
     * @var string
     */
    const GATE_SANDBOX_URL = 'https://api.yekpay.com/api/sandbox/payment/';

    /**
     * Address of main server
     *
     * @var string
     */
    protected $serverUrl;

    /**
     * Address of main gate for redirect
     *
     * @var string
     */
    protected $gateUrl;

    public function __construct(Container $app, array $config)
    {
        parent::__construct($app, $config);

        $this->setServer();
    }


    /**
     * Set server for soap transfers data
     *
     * @return void
     */
    protected function setServer()
    {
        $server = Arr::get($this->config, 'server', 'main');
        switch ($server) {
            case 'main':
                $this->serverUrl = self::SERVER_URL;
                $this->gateUrl = self::GATE_URL;
                break;

            case 'test':
                $this->serverUrl = self::SERVER_SANDBOX_URL;
                $this->gateUrl = self::GATE_SANDBOX_URL;
                break;
        }
    }


    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::YEKPAY;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $amount = $transaction->getAmount();
        if ($amount->getCurrency() == 'IRT') {
            $amount = new Amount($amount->getRiyal(), 'IRR');
        }

        $fields = [
            'merchantId'       => $this->config['merchant-id'],
            // the actual currency of amount to pay
            'fromCurrencyCode' => $this->convertCurrencyCode($amount->getCurrency()),
            // the currency that payer pays with
            'toCurrencyCode'   => $this->convertCurrencyCode(
                $transaction->getExtraField('to_currency_code', $amount->getCurrency())
            ),
            'email'            => $transaction->getExtraField('email'),
            'mobile'           => $transaction->getExtraField('mobile'),
            'firstName'        => $transaction->getExtraField('first_name'),
            'lastName'         => $transaction->getExtraField('last_name'),
            'address'          => $transaction->getExtraField('address'),
            'postalCode'       => $transaction->getExtraField('postal_code'),
            'country'          => $transaction->getExtraField('country'),
            'city'             => $transaction->getExtraField('city'),
            'description'      => $transaction->getExtraField('description'),
            'amount'           => number_format($amount->getTotal(), 2),
            'orderNumber'      => $transaction->getOrderId(),
            'callback'         => $this->getCallback($transaction),
        ];

        list($result, $http_code) = Curl::execute($this->serverUrl.'/request', $fields, false);

        if ($http_code != 200 || empty($result->Code) || $result->Code != 100) {
            throw new YekPayException($result->Code ?? $http_code, $result->Description ?? null);
        }

        $gateUrl = $this->gateUrl.$result->Authority;

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $gateUrl);

        return AuthorizedTransaction::make($transaction, $result->Authority, null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $success = $request->input('success', $request->input('status'));
        if ($success != '1') {
            throw new YekPayException($success, $request->input('description', 'YekPay Payment Cancelled'));
        }

        $referenceId = $request->input('authority');

        return new FieldsToMatch(null, $referenceId);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $fields = [
            'merchantId' => $this->config['merchant-id'],
            'authority'  => $transaction->getReferenceId(),
        ];

        list($result, $http_code) = Curl::execute($this->serverUrl.'/verify', $fields);

        if ($http_code != 200 || empty($result['Code']) || $result['Code'] != 100) {
            throw new YekPayException($result['Code'] ?? $http_code, $result['Description'] ?? null);
        }

        if (empty($result['status']) || $result['status'] != 1) {
            throw new YekPayException(
                $result['status'] ?? $request->input('status', $request->input('error_code')),
                $result['Description'] ?? $request->input('description')
            );
        }

        $authority = $result['Authority'];
//        $reference = $result['Reference'];

        $toMatch = new FieldsToMatch($result['Order number'], $authority);

        return new SettledTransaction(
            $transaction,
            $authority,
            $toMatch,
            '',
            '',
            ['verify_result' => $result]
        );
    }

    /**
     * @param int|string $code
     * @return int|string|null
     */
    protected function convertCurrencyCode($code)
    {
        $currencyCodes = [
            978 => 'EUR', // Euro
            364 => 'IRR', // Iranian Rial
            756 => 'CHF', // Switzerland Franc
            784 => 'AED', // United Arab Emirates Dirham
            156 => 'CNY', // Chinese Yuan
            826 => 'GBP', // British Pound
            392 => 'JPY', // Japanese 100 Yens
            643 => 'RUB', // Russian Ruble
            949 => 'TRY', // Turkish New Lira

            'EUR' => 978, // Euro
            'IRR' => 364, // Iranian Rial
            'CHF' => 756, // Switzerland Franc
            'AED' => 784, // United Arab Emirates Dirham
            'CNY' => 156, // Chinese Yuan
            'GBP' => 826, // British Pound
            'JPY' => 392, // Japanese 100 Yens
            'RUB' => 643, // Russian Ruble
            'TRY' => 949, // Turkish New Lira
        ];

        return $currencyCodes[$code] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtraFieldsSample()
    {
        return [
            'mobile'           => '+44123456789',
            'to_currency_code' => 'EUR (Euro) ||'.
                ' IRR (Iranian Rial) ||'.
                ' CHF (Switzerland Franc) ||'.
                ' AED (United Arab Emirates Dirham) ||'.
                ' CNY (Chinese Yuan) ||'.
                ' GBP (British Pound) ||'.
                ' JPY (Japanese 100 Yens) ||'.
                ' RUB (Russian Ruble) ||'.
                ' TRY (Turkish New Lira)',
            'email'            => 'test@gmail.com',
            'first_name'       => 'John',
            'last_name'        => 'Doe',
            'address'          => 'Alhamida st Al ras st',
            'postal_code'      => '64785',
            'country'          => 'United Arab Emirates',
            'city'             => 'Dubai',
            'description'      => 'Apple mac book air 2017',
        ];
    }
}
