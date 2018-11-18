<?php

namespace Parsisolution\Gateway\Providers\Pardano;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;


class Pardano extends AbstractProvider {

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
        if ($api == 'test')
        {
            $this->server_url = self::SERVER_TEST;
            $this->gate_url = self::GATE_URL_TEST;
        } else
        {
            $this->server_url = self::SERVER_MAIN;
            $this->gate_url = self::GATE_URL;
        }
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
        return GatewayManager::PARDANO;
    }

    /**
     * Authorize payment request from provider's server and return
     * authorization response as AuthorizedTransaction
     * or throw an Error (most probably SoapFault)
     *
     * @param UnAuthorizedTransaction $transaction
     * @return AuthorizedTransaction
     * @throws Exception
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $client = new SoapClient($this->server_url, $this->SoapConfig());
        $api = $this->config['api'];
        $amount = $transaction->getAmount()->getToman();
        $callbackUrl = $this->getCallback($transaction);
        $orderId = $transaction->getExtraField('order.id', 1);
        $txt = $transaction->getExtraField('description');
        $res = $client->requestpayment($api, $amount, $callbackUrl, $orderId, $txt);

        return AuthorizedTransaction::make($transaction, $res);
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Parsisolution\Gateway\Transactions\AuthorizedTransaction $transaction
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        return new RedirectResponse($this->gate_url . $transaction->getReferenceId());
    }

    /**
     * Validate the settlement request to see if it has all necessary fields
     *
     * @param Request $request
     * @return bool
     * @throws InvalidRequestException
     */
    protected function validateSettlementRequest(Request $request)
    {
        $orderId = $request->input('order_id');
        $authority = $request->input('au');

        if (isset($orderId) && isset($authority))
            return true;

        throw new InvalidRequestException();
    }

    /**
     * Verify and Settle the transaction and return
     * settlement response as SettledTransaction
     * or throw a TransactionException
     *
     * @param Request $request
     * @param AuthorizedTransaction $transaction
     * @return SettledTransaction
     * @throws TransactionException
     * @throws Exception
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $authority = $request->input('au');
        $api = $this->config['api'];
        $amount = $transaction->getAmount()->getToman();
        $client = new SoapClient($this->server_url, $this->SoapConfig());
        $result = $client->verification($api, $amount, $authority);

        if (! empty($result) and $result == 1)
        {
            return new SettledTransaction($transaction, $authority);
        } else
        {
            throw new PardanoException($result);
        }
    }
}