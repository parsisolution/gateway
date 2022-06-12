<?php

namespace Parsisolution\Gateway\Providers\IranDargah;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class IranDargah extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of server
     *
     * @var string
     */
    const SERVER_URL = 'https://dargaah.com';

    /**
     * Address of sandbox server
     *
     * @var string
     */
    const SERVER_SANDBOX_URL = 'https://dargaah.com/sandbox';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://dargaah.com/ird/startpay/';

    /**
     * Address of sandbox gate for redirect
     *
     * @var string
     */
    const GATE_SANDBOX_URL = 'https://dargaah.com/sandbox/ird/startpay/';

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

    /**
     * Merchant Id
     *
     * @var string
     */
    protected $merchantId;

    /**
     * Payment Description
     *
     * @var string
     */
    protected $description;

    /**
     * Payer Card Number
     *
     * @var string
     */
    protected $cardNumber;

    /**
     * Payer Mobile Number
     *
     * @var string
     */
    protected $mobileNumber;

    public function __construct(Container $app, array $config)
    {
        parent::__construct($app, $config);

        $this->setServer();

        return $this;
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
                $this->merchantId = Arr::get($this->config, 'merchant-id');
                break;

            case 'test':
                $this->serverUrl = self::SERVER_SANDBOX_URL;
                $this->gateUrl = self::GATE_SANDBOX_URL;
                $this->merchantId = 'TEST';
                break;
        }
    }

    /**
     * Set Description
     *
     * @param $description
     * @return void
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Set Payer Card Number
     *
     * @param $cardNumber
     * @return void
     */
    public function setCardNumber($cardNumber)
    {
        $this->cardNumber = $cardNumber;
    }

    /**
     * Set Payer Mobile Number
     *
     * @param $number
     * @return void
     */
    public function setMobileNumber($number)
    {
        $this->mobileNumber = $number;
    }

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::IRANDARGAH;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'merchantID'  => $this->merchantId,
            'amount'      => $transaction->getAmount()->getRiyal(),
            'callbackURL' => $this->getCallback($transaction),
            'orderId'     => $transaction->getOrderId(),
            'mobile'      => $transaction->getExtraField('mobile', $this->mobileNumber),
            'description' => $transaction->getExtraField('description', Arr::get($this->config, 'description', '')),
        ];
        $cardNumber = $transaction->getExtraField('cardNumber', $this->cardNumber);
        if (! empty($cardNumber)) {
            // by sending cardnumber , your user can not pay with another card number // OPTIONAL
            $fields['cardNumber'] = $cardNumber;
        }
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->serverUrl.'/payment');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        # if you get SSL error (curl error 60) add 2 lines below
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        # end SSL error
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        $result = json_decode($response);

        if (isset($result->status) && $result->status == '200') {
            return AuthorizedTransaction::make($transaction, $result->authority);
        } else {
            throw new IranDargahException(isset($result->status) ? $result->status : $http_code, $result->message);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        return new RedirectResponse(RedirectResponse::TYPE_GET, $this->gateUrl.$transaction->getReferenceId());
    }

    /**
     * @inheritdoc
     */
    protected function validateSettlementRequest(Request $request)
    {
        if (! $request->has('code')) {
            throw new InvalidRequestException();
        }

        $code = $request->input('code');
        if ($code != 100) {
            throw new IranDargahException($code/*, $request->input('message')*/);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $authority = $request->input('authority');

        $fields = [
            'merchantID' => $this->merchantId,
            'authority'  => $authority,
            'amount'     => $transaction->getAmount()->getRiyal(),
            'orderId'    => $transaction->getOrderId(),
        ];
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->serverUrl.'/verification');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        # if you get SSL error (curl error 60) add 2 lines below
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        # end SSL error
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);

        curl_close($ch);
        $result = json_decode($response);

//        $status = $result->status;
//        $message = $result->message;
//        $orderId = $result->orderId;

        return new SettledTransaction($transaction, $result->refId, $result->cardNumber);
    }
}
