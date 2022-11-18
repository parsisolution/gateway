<?php

namespace Parsisolution\Gateway\Providers\Sepal;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

class Sepal extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://sepal.ir/api/';

    /**
     * Address of sandbox server
     *
     * @var string
     */
    const SERVER_SANDBOX_URL = 'https://sepal.ir/api/sandbox/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://sepal.ir/payment/';

    /**
     * Address of sandbox gate for redirect
     *
     * @var string
     */
    const GATE_SANDBOX_URL = 'https://sepal.ir/sandbox/payment/';

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
        if (Arr::get($this->config, 'sandbox', false)) {
            $this->serverUrl = self::SERVER_SANDBOX_URL;
            $this->gateUrl = self::GATE_SANDBOX_URL;
        } else {
            $this->serverUrl = self::SERVER_URL;
            $this->gateUrl = self::GATE_URL;
        }
    }


    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::SEPAL;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'amount'        => $transaction->getAmount()->getRiyal(),
            'callbackUrl'   => $this->getCallback($transaction),
            'invoiceNumber' => $transaction->getOrderId(),
            'payerName'     => $transaction->getExtraField('name'),
            'payerMobile'   => $transaction->getExtraField('mobile'),
            'payerEmail'    => $transaction->getExtraField('email'),
            'description'   => $transaction->getExtraField('description'),
            'affiliateCode' => $transaction->getExtraField('affiliate_code'),
        ];

        $result = $this->callApi('request.json', $fields);

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $this->gateUrl . $result['paymentNumber']);

        return AuthorizedTransaction::make($transaction, $result['paymentNumber'], null, $redirectResponse);
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
            throw new SepalException($status);
        }

        return new FieldsToMatch($request->input('invoiceNumber'), $request->input('paymentNumber'));
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $result = $this->callApi('verify.json', ['paymentNumber' => $transaction->getReferenceId()]);

        $cardNumber = $result['cardNumber'] ?? '';

        return new SettledTransaction(
            $transaction,
            $transaction->getReferenceId(),
            new FieldsToMatch(),
            $cardNumber,
            '',
            ['verify_result' => $result]
        );
    }

    /**
     * @param string $path
     * @param array $fields
     * @return mixed
     * @throws SepalException
     */
    protected function callApi(string $path, array $fields)
    {
        $fields['apiKey'] = $this->config['api-key'];
        list($response, $http_code, $error) = Curl::execute($this->serverUrl . $path, $fields);

        if ($http_code != 200 || empty($response['status'])) {
            throw new SepalException(
                $response['status'] ?? $http_code,
                $response['message'] ?? $error ?? null
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
            'mobile'         => '09124441122',
            'name'           => 'نام پرداخت کننده',
            'email'          => 'test@gmail.com',
            'description'    => 'توضیحات مربوط به تراکنش که به صورت اختیاری می باشد',
            'affiliate_code' => 'کد معرف، در صورتی که این کد ارسال شود بخشی از کارمزد تراکنش به معرف تعلق میگیرد',
        ];
    }
}
