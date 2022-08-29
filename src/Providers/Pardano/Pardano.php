<?php

namespace Parsisolution\Gateway\Providers\Pardano;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\RedirectResponse;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;

class Pardano extends AbstractProvider
{

    const SERVER_MAIN = 'http://pardano.com/p/webservice/?wsdl';
    const SERVER_TEST = 'http://pardano.com/p/webservice-test/?wsdl';

    const GATE_URL = 'http://pardano.com/p/payment/';
    const GATE_URL_TEST = 'http://pardano.com/p/payment-test/';

    private $server_url;
    private $gate_url;

    public function __construct(Container $app, array $config)
    {
        parent::__construct($app, $config);

        $api = $this->config['api'];
        if ($api == 'test') {
            $this->server_url = self::SERVER_TEST;
            $this->gate_url = self::GATE_URL_TEST;
        } else {
            $this->server_url = self::SERVER_MAIN;
            $this->gate_url = self::GATE_URL;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getProviderId()
    {
        return GatewayManager::PARDANO;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $client = new SoapClient($this->server_url, $this->soapConfig());
        $api = $this->config['api'];
        $amount = $transaction->getAmount()->getToman();
        $callbackUrl = $this->getCallback($transaction);
        $orderId = $transaction->getOrderId();
        $txt = $transaction->getExtraField('description');
        $res = $client->requestpayment($api, $amount, $callbackUrl, $orderId, $txt);

        $redirectResponse = new RedirectResponse(RedirectResponse::TYPE_GET, $this->gate_url.$res);

        return AuthorizedTransaction::make($transaction, $res, null, $redirectResponse);
    }

    /**
     * {@inheritdoc}
     */
    protected function validateSettlementRequest(Request $request)
    {
        $orderId = $request->input('order_id');
        $authority = $request->input('au');

        if (! isset($orderId) || ! isset($authority)) {
            throw new InvalidRequestException();
        }

        return new FieldsToMatch($orderId);
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $authority = $request->input('au');
        $api = $this->config['api'];
        $amount = $transaction->getAmount()->getToman();
        $client = new SoapClient($this->server_url, $this->soapConfig());
        $result = $client->verification($api, $amount, $authority);

        if (empty($result) or $result != 1) {
            throw new PardanoException($result);
        }

        return new SettledTransaction($transaction, $authority, new FieldsToMatch());
    }
}
