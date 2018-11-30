<?php

namespace Parsisolution\Gateway\Providers\Zarinpal;

use Illuminate\Container\Container;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Contracts\Provider as ProviderInterface;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Zarinpal extends AbstractProvider implements ProviderInterface
{

    /**
     * Address of germany SOAP server
     *
     * @var string
     */
    const SERVER_GERMANY = 'https://de.zarinpal.com/pg/services/WebGate/wsdl';

    /**
     * Address of iran SOAP server
     *
     * @var string
     */
    const SERVER_IRAN = 'https://ir.zarinpal.com/pg/services/WebGate/wsdl';

    /**
     * Address of sandbox SOAP server
     *
     * @var string
     */
    const SERVER_SANDBOX = 'https://sandbox.zarinpal.com/pg/services/WebGate/wsdl';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'https://www.zarinpal.com/pg/StartPay/';

    /**
     * Address of sandbox gate for redirect
     *
     * @var string
     */
    const GATE_URL_SANDBOX = 'https://sandbox.zarinpal.com/pg/StartPay/';

    /**
     * Address of zarin gate for redirect
     *
     * @var string
     */
    const GATE_URL_ZARIN = 'https://www.zarinpal.com/pg/StartPay/$Authority/ZarinGate';

    /**
     * Address of zarin gate for redirect To Saman Bank
     *
     * @var string
     */
    const GATE_URL_SEP = 'https://www.zarinpal.com/pg/StartPay/$Authority/Sep';

    /**
     * Address of zarin gate for redirect To Melli Bank
     *
     * @var string
     */
    const GATE_URL_SAD = 'https://www.zarinpal.com/pg/StartPay/$Authority/Sad';

    /**
     * Address of main SOAP server
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
     * Type of zarinpal : `normal`, `zarin-gate`, `zarin-gate-sep`, `zarin-gate-sad`
     *
     * @var string
     */
    protected $type = null;

    /**
     * Payment Description
     *
     * @var string
     */
    protected $description;

    /**
     * Payer Email Address
     *
     * @var string
     */
    protected $email;

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
        $server = Arr::get($this->config, 'server', 'germany');
        switch ($server) {
            case 'iran':
                $this->serverUrl = self::SERVER_IRAN;
                $this->gateUrl = self::GATE_URL;
                break;

            case 'test':
                $this->serverUrl = self::SERVER_SANDBOX;
                $this->gateUrl = self::GATE_URL_SANDBOX;
                break;

            case 'germany':
            default:
                $this->serverUrl = self::SERVER_GERMANY;
                $this->gateUrl = self::GATE_URL;
                break;
        }
    }

    /**
     * Set ZarinPal gate transaction type (test or production | server location)
     *
     * @param string $type
     * @return Zarinpal
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
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
     * Set Payer Email Address
     *
     * @param $email
     * @return void
     */
    public function setEmail($email)
    {
        $this->email = $email;
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
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
    {
        return GatewayManager::ZARINPAL;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = array(
            'MerchantID'  => Arr::get($this->config, 'merchant-id'),
            'Amount'      => $transaction->getAmount()->getToman(),
            'CallbackURL' => $this->getCallback($transaction),
            'Description' => $transaction->getExtraField('description', Arr::get($this->config, 'description', '')),
            'Email'       => $transaction->getExtraField('email'),
            'Mobile'      => $transaction->getExtraField('mobile'),
        );

        $soap = new SoapClient($this->serverUrl, $this->soapConfig(), ['encoding' => 'UTF-8']);
        $response = $soap->PaymentRequest($fields);

        if ($response->Status != 100) {
            throw new ZarinpalException($response->Status);
        }

        return AuthorizedTransaction::make($transaction, $response->Authority);
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param AuthorizedTransaction $transaction
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        if (! $this->type) {
            $this->type = Arr::get($this->config, 'type');
        }

        switch ($this->type) {
            case 'zarin-gate':
                return new RedirectResponse(
                    str_replace('$Authority', $transaction->getReferenceId(), self::GATE_URL_ZARIN)
                );
                break;

            case 'zarin-gate-sep':
                return new RedirectResponse(
                    str_replace('$Authority', $transaction->getReferenceId(), self::GATE_URL_SEP)
                );
                break;

            case 'zarin-gate-sad':
                return new RedirectResponse(
                    str_replace('$Authority', $transaction->getReferenceId(), self::GATE_URL_SAD)
                );
                break;

            case 'normal':
            default:
                return new RedirectResponse($this->gateUrl.$transaction->getReferenceId());
                break;
        }
    }

    /**
     * @inheritdoc
     */
    protected function validateSettlementRequest(Request $request)
    {
        $status = $request->input('Status');

        if ($status == 'OK') {
            return true;
        }

        throw new InvalidRequestException();
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $authority = $request->input('Authority');

        $fields = [
            'MerchantID' => Arr::get($this->config, 'merchant-id'),
            'Authority'  => $authority,
            'Amount'     => $transaction->getAmount()->getToman(),
        ];

        $soap = new SoapClient($this->serverUrl, $this->soapConfig(), ['encoding' => 'UTF-8']);
        $response = $soap->PaymentVerification($fields);

        if ($response->Status != 100 && $response->Status != 101) {
            throw new ZarinpalException($response->Status);
        }

        return new SettledTransaction($transaction, $response->RefID);
    }
}
