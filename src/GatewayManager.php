<?php

namespace Parsisolution\Gateway;

use Illuminate\Support\Arr;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Parsisolution\Gateway\Exceptions\GatewayException;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\Exceptions\InvalidStateException;
use Parsisolution\Gateway\Exceptions\NotFoundTransactionException;
use Parsisolution\Gateway\Exceptions\NullConfigException;
use Parsisolution\Gateway\Exceptions\RetryException;
use Parsisolution\Gateway\Providers\Asanpardakht\Asanpardakht;
use Parsisolution\Gateway\Providers\Irankish\Irankish;
use Parsisolution\Gateway\Providers\JiBit\JiBit;
use Parsisolution\Gateway\Providers\Mabna\Mabna;
use Parsisolution\Gateway\Providers\MabnaOld\MabnaOld;
use Parsisolution\Gateway\Providers\Mellat\Mellat;
use Parsisolution\Gateway\Providers\NextPay\NextPay;
use Parsisolution\Gateway\Providers\Pardano\Pardano;
use Parsisolution\Gateway\Providers\Parsian\Parsian;
use Parsisolution\Gateway\Providers\Payir\Payir;
use Parsisolution\Gateway\Providers\SabaPay\SabaPay;
use Parsisolution\Gateway\Providers\Sadad\Sadad;
use Parsisolution\Gateway\Providers\Saman\Saman;
use Parsisolution\Gateway\Providers\Zarinpal\Zarinpal;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;

class GatewayManager extends Manager implements Contracts\Factory
{

    const CONFIG_FILE_NAME = 'gateways';

    const MELLAT = 'MELLAT';
    const SADAD = 'SADAD';
    const SAMAN = 'SAMAN';
    const PARSIAN = 'PARSIAN';
    const MABNA = 'MABNA';
    const MABNA_OLD = 'MABNA_OLD';
    const IRANKISH = 'IRANKISH';
    const ASANPARDAKHT = 'ASANPARDAKHT';
    const PAYIR = 'PAYIR';
    const PARDANO = 'PARDANO';
    const ZARINPAL = 'ZARINPAL';
    const NEXTPAY = 'NEXTPAY';
    const JIBIT = 'JIBIT';
    const SABAPAY = 'SABAPAY';

    /**
     * Get all of the available "drivers".
     *
     * @return array
     */
    public static function availableDrivers()
    {
        return [
            self::MELLAT,
            self::SADAD,
            self::SAMAN,
            self::PARSIAN,
            self::MABNA,
            self::MABNA_OLD,
            self::IRANKISH,
            self::ASANPARDAKHT,
            self::PAYIR,
            self::PARDANO,
            self::ZARINPAL,
            self::NEXTPAY,
            self::JIBIT,
            self::SABAPAY,
        ];
    }

    /**
     * Get all of the active "drivers" with their names and in specified order.
     *
     * @param string $name_prefix
     * @return array
     */
    public static function activeDrivers($name_prefix = 'درگاه ')
    {
        $activeDrivers = [];
        $configsOfDrivers = $this->app['config'][self::CONFIG_FILE_NAME];

        foreach ($configsOfDrivers as $driverKey => $driverConfig) {
            if (Arr::get($driverConfig, 'active', false)) {
                $activeDrivers[$driverConfig['order']] = [
                    'key'  => $driverKey,
                    'name' => $name_prefix.$driverConfig['name'],
                ];
            }
        }

        ksort($activeDrivers);

        return array_values($activeDrivers);
    }

    /**
     * Get a driver instance.
     *
     * @param  string $driver
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    public function of($driver)
    {
        return $this->driver($driver);
    }

    /**
     * retrieve respective transaction from request
     *
     * @param bool $stateless
     *
     * @return \Parsisolution\Gateway\Transactions\AuthorizedTransaction
     * @throws \Parsisolution\Gateway\Exceptions\InvalidRequestException
     * @throws \Parsisolution\Gateway\Exceptions\InvalidStateException
     * @throws \Parsisolution\Gateway\Exceptions\NotFoundTransactionException
     * @throws \Parsisolution\Gateway\Exceptions\RetryException
     */
    public function transactionFromSettleRequest($stateless = false)
    {
        $request = $this->app['request'];
        $parameters = [];

        if (! $stateless) {
            if ($this->hasInvalidState($stateless)) {
                throw new InvalidStateException;
            }

            $all = $request->session()->all();
            $to_forgets = [];
            foreach ($all as $key => $value) {
                if (starts_with($key, 'gateway_')) {
                    $name = substr($key, 8);

                    $parameters [$name] = $value;
                    $to_forgets [] = $key;
                }
            }
            $request->session()->forget($to_forgets);
        } else {
            $parameters = $request->input();
        }

        if (! key_exists('transaction_id', $parameters) && ! key_exists('iN', $parameters)) {
            throw new InvalidRequestException;
        }
        if (key_exists('transaction_id', $parameters)) {
            $id = $parameters['transaction_id'];
        } else {
            $id = $parameters['iN'];
        }

        $db = $this->app['db'];
        $transaction = $db->table($this->getTable())->where('id', $id)->first();

        if (! $transaction) {
            throw new NotFoundTransactionException;
        }

        if (in_array($transaction->status, [Transaction::STATE_SUCCEEDED, Transaction::STATE_FAILED])) {
            throw new RetryException;
        }

        return AuthorizedTransaction::makeFromDB(get_object_vars($transaction));
    }

    /**
     * Verify and Settle the callback request and get the settled transaction instance.
     *
     * @param bool $stateless
     *
     * @return \Parsisolution\Gateway\Transactions\SettledTransaction
     * @throws \Parsisolution\Gateway\Exceptions\TransactionException
     * @throws \Parsisolution\Gateway\Exceptions\InvalidRequestException
     * @throws \Parsisolution\Gateway\Exceptions\NotFoundTransactionException
     * @throws \Parsisolution\Gateway\Exceptions\RetryException
     * @throws \Parsisolution\Gateway\Exceptions\InvalidStateException
     */
    public function settle($stateless = false)
    {
        $authorizedTransaction = $this->transactionFromSettleRequest($stateless);

        $driver = $this->of(strtoupper($authorizedTransaction['provider']));
        $driver->setTransactionId($authorizedTransaction->getId());
        if ($stateless) {
            $driver->stateless();
        }

        return $driver->settle($authorizedTransaction);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createMellatDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.mellat'];

        return $this->buildProvider(Mellat::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createSadadDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.sadad'];

        return $this->buildProvider(Sadad::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createSamanDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.saman'];

        return $this->buildProvider(Saman::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createParsianDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.parsian'];

        return $this->buildProvider(Parsian::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createMabnaDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.mabna'];

        return $this->buildProvider(Mabna::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createMabnaOldDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.mabna-old'];

        return $this->buildProvider(MabnaOld::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createIrankishDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.irankish'];

        return $this->buildProvider(Irankish::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createAsanpardakhtDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.asanpardakht'];

        return $this->buildProvider(Asanpardakht::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createPayirDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.payir'];

        return $this->buildProvider(Payir::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createPardanoDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.pardano'];

        return $this->buildProvider(Pardano::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createZarinpalDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.zarinpal'];

        return $this->buildProvider(Zarinpal::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createNextpayDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.nextpay'];

        return $this->buildProvider(NextPay::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createJibitDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.jibit'];

        return $this->buildProvider(JiBit::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createSabapayDriver()
    {
        $config = $this->app['config'][self::CONFIG_FILE_NAME.'.sabapay'];

        return $this->buildProvider(SabaPay::class, $config);
    }

    /**
     * Get transactions table name
     *
     * @return string
     */
    private function getTable()
    {
        return Arr::get($this->app['config'], self::CONFIG_FILE_NAME.'.table', 'gateway_transactions');
    }

    /**
     * Build a Gateway provider instance.
     *
     * @param  string $provider
     * @param  array $config
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    public function buildProvider($provider, $config)
    {
        if (! $config) {
            throw new NullConfigException();
        }

        return new $provider($this->app, $this->formatConfig($config));
    }

    /**
     * Format the server configuration.
     *
     * @param  array $config
     * @return array
     */
    public function formatConfig(array $config)
    {
        return array_merge([
            'callback-url' => $this->formatCallbackUrl($config),
            'settings'     => [
                'soap' => Arr::get($this->app['config'], 'soap', []),
            ],
        ], $config);
    }

    /**
     * Format the callback URL, resolving a relative URI if needed.
     *
     * @param  array $config
     * @return string
     */
    protected function formatCallbackUrl(array $config)
    {
        $redirect = value($config['callback-url']);

        return Str::startsWith($redirect, '/')
            ? $this->app['url']->to($redirect)
            : $redirect;
    }

    /**
     * Determine if the current request / session has a mismatching "state".
     *
     * @param bool $stateless
     * @return bool
     */
    protected function hasInvalidState($stateless = false)
    {
        if ($stateless) {
            return false;
        }

        $state = $this->app['request']->session()->pull('gateway__state');

        return ! (strlen($state) > 0 && $this->app['request']->input('_state') === $state);
    }

    /**
     * Get the default driver name.
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        throw new InvalidArgumentException('No Gateway driver was specified.');
    }
}
