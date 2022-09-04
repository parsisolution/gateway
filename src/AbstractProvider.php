<?php

namespace Parsisolution\Gateway;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Parsisolution\Gateway\Contracts\Provider as ProviderContract;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\Exceptions\RetryException;
use Parsisolution\Gateway\Exceptions\TransactionException;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\FieldsToMatch;
use Parsisolution\Gateway\Transactions\RequestTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;
use RuntimeException;

abstract class AbstractProvider implements ProviderContract
{


    /**
     * @var \Illuminate\Container\Container
     */
    protected $app;

    /**
     * The HTTP request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * provider configurations
     *
     * @var array
     */
    protected $config;

    /**
     * The custom parameters to be sent with the request.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * Indicates if the session state should be utilized.
     *
     * @var bool
     */
    protected $stateless = false;

    /**
     * Callback URL
     *
     * @var string
     */
    private $callbackUrl;

    /**
     * Transaction Data Access Object
     *
     * @var TransactionDao
     */
    protected $transactionDao;

    /**
     * Create a new provider instance.
     *
     * @param  \Illuminate\Container\Container $app
     * @param  array $config
     */
    public function __construct(Container $app, $config)
    {
        $this->app = $app;
        $this->config = $config;

        $this->request = $app->make('request');

        $table_name = Arr::get($this->app['config'], GatewayManager::CONFIG_FILE_NAME.'.table', 'gateway_transactions');
        $this->transactionDao = new TransactionDao($app->make('db'), $table_name);
    }

    /**
     * {@inheritdoc}
     */
    public function callbackUrl($url)
    {
        $this->callbackUrl = $url;

        return $this;
    }

    /**
     * Gets callback url
     *
     * @param UnAuthorizedTransaction $transaction
     * @param bool $encode
     * @return string
     */
    public function getCallback(UnAuthorizedTransaction $transaction, $encode = false)
    {
        if (! $this->callbackUrl) {
            $this->callbackUrl = Arr::get($this->config, 'callback-url');
        }

        $callback = $this->makeCallback($transaction);

        return $encode ? urlencode($callback) : $callback;
    }

    /**
     * Get this provider id to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return integer
     */
    abstract protected function getProviderId();

    /**
     * Authorize payment request from provider's server and return
     * authorization response as AuthorizedTransaction
     * or throw an Error (most probably SoapFault)
     *
     * @param UnAuthorizedTransaction $transaction
     * @return AuthorizedTransaction
     * @throws Exception
     */
    abstract protected function authorizeTransaction(UnAuthorizedTransaction $transaction);

    /**
     * {@inheritdoc}
     */
    final public function authorize(RequestTransaction $transaction)
    {
        $unAuthorizedTransaction = $this->transactionDao->create(
            $transaction,
            $this->getProviderId(),
            $this->request->getClientIp()
        );

        try {
            $authorizedTransaction = $this->authorizeTransaction($unAuthorizedTransaction);

            $this->transactionDao->updateTransaction($authorizedTransaction);

            return $authorizedTransaction;
        } catch (Exception $e) {
            $this->transactionDao->failed(
                $unAuthorizedTransaction,
                get_class($e).' : '.$e->getCode(),
                $e->getMessage()
            );
            throw $e;
        }
    }

    /**
     * Validate the settlement request to see if it has all necessary fields
     *
     * @param Request $request
     * @return FieldsToMatch
     * @throws InvalidRequestException|TransactionException
     */
    abstract protected function validateSettlementRequest(Request $request);

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
    abstract protected function settleTransaction(Request $request, AuthorizedTransaction $transaction);

    /**
     * {@inheritdoc}
     */
    final public function settle(AuthorizedTransaction $authorizedTransaction, $fieldsToUpdateOnSuccess = [])
    {
        try {
            $fieldsToMatch = $this->validateSettlementRequest($this->request);

            if (! $fieldsToMatch->matches($authorizedTransaction)) {
                throw new InvalidRequestException();
            }

            $settledTransaction = $this->settleTransaction($this->request, $authorizedTransaction);

            if (! $settledTransaction->getFieldsToMatch()->matches($settledTransaction)) {
                throw new InvalidRequestException();
            }

            // prevent "Double Spending"
            if ($this->transactionDao->isSpent($settledTransaction)) {
                throw new RetryException('The Transaction has been Spent Before');
            }

            $this->transactionDao->succeeded($settledTransaction, $fieldsToUpdateOnSuccess);

            return $settledTransaction;
        } catch (TransactionException $exception) {
            $this->transactionDao->failed($authorizedTransaction, $exception->getCode(), $exception->getMessage());

            throw $exception;
        } catch (Exception $e) {
            $this->transactionDao->failed($authorizedTransaction, get_class($e).' : '.$e->getCode(), $e->getMessage());

            throw $e;
        }
    }

    /**
     * Set the request instance.
     *
     * @param  \Illuminate\Http\Request $request
     * @return self
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Determine if the provider is operating with state.
     *
     * @return bool
     */
    protected function usesState()
    {
        return ! $this->stateless;
    }

    /**
     * Determine if the provider is operating as stateless.
     *
     * @return bool
     */
    protected function isStateless()
    {
        return $this->stateless;
    }

    /**
     * Indicates that the provider should operate as stateless.
     *
     * @return self
     */
    public function stateless()
    {
        $this->stateless = true;

        return $this;
    }

    /**
     * Get the string used for session state.
     *
     * @return string
     */
    protected function getState()
    {
        return Str::random(40);
    }

    /**
     * Set the custom parameters of the request.
     *
     * @param  array $parameters
     * @return self
     */
    public function with(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Get supported extra fields sample
     *
     * @return array
     */
    public function getSupportedExtraFieldsSample()
    {
        return [];
    }

    /**
     * Get the CSRF token value.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function csrfToken()
    {
        $session = $this->app->make('session');

        if (isset($session)) {
            return $session->token();
        }

        throw new RuntimeException('Application session store not set.');
    }

    /**
     * Add (required) query string to a (callback) url
     *
     * @param UnAuthorizedTransaction $transaction
     * @param string|null $url
     * @return string
     */
    private function makeCallback(UnAuthorizedTransaction $transaction, $url = null)
    {
        if ($url == null && $this->callbackUrl == null) {
            throw new \InvalidArgumentException('callback url is not set');
        }

        $this->with(array_merge(
            $this->parameters,
            ['_order_id' => $transaction->getOrderId()]
        ));

        if ($this->usesState()) {
            $this->with(array_merge(
                $this->parameters,
                ['_state' => $this->getState()]
            ));

            $changes = ['_token' => $this->csrfToken(), '_state' => $this->parameters['_state']];
            foreach ($this->parameters as $key => $value) {
                $this->request->session()->put('gateway_'.$key, $value);
            }
        } else {
            $changes = array_merge(
                ['_token' => $this->csrfToken()],
                $this->parameters
            );
        }

        return $this->modifyUrl(
            $changes,
            $this->app->make('url')->to($url ?: $this->callbackUrl)
        );
    }

    /**
     * manipulate the given URL with the given parameters
     *
     * @param  array $changes
     * @param  string $url
     * @return string
     */
    protected function modifyUrl($changes, $url)
    {
        // Parse the url into pieces
        $url_array = parse_url($url);

        // The original URL had a query string, modify it.
        if (! empty($url_array['query'])) {
            parse_str($url_array['query'], $query_array);
            $query_array = array_merge($query_array, $changes);
        } else {
            // The original URL didn't have a query string, add it.
            $query_array = $changes;
        }

        return (! empty($url_array['scheme']) ? $url_array['scheme'].'://' : null).
            (! empty($url_array['host']) ? $url_array['host'] : null).
            (! empty($url_array['port']) ? ':'.$url_array['port'] : null).
            (! empty($url_array['path']) ? $url_array['path'] : '').
            '?'.http_build_query($query_array);
    }

    /**
     * @return array
     */
    protected function soapConfig()
    {
        return Arr::get($this->config, 'settings.soap');
    }
}
