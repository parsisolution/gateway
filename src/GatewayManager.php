<?php

namespace Parsisolution\Gateway;

use Illuminate\Support\Arr;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\Exceptions\InvalidStateException;
use Parsisolution\Gateway\Exceptions\NotFoundTransactionException;
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
use Parsisolution\Gateway\Providers\PayPing\PayPing;
use Parsisolution\Gateway\Providers\SabaPay\SabaPay;
use Parsisolution\Gateway\Providers\Sadad\Sadad;
use Parsisolution\Gateway\Providers\Saman\Saman;
use Parsisolution\Gateway\Providers\Sizpay\Sizpay;
use Parsisolution\Gateway\Providers\Zarinpal\Zarinpal;

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
    const SIZPAY = 'SIZPAY';
    const PAYPING = 'PAYPING';

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
            self::SIZPAY,
            self::PAYPING,
        ];
    }

    /**
     * Get all of the active "drivers" with their names and in specified order.
     *
     * @param string $name_prefix
     * @return array
     */
    public function activeDrivers($name_prefix = 'درگاه ')
    {
        $activeDrivers = [];
        $configsOfDrivers = app()['config'][self::CONFIG_FILE_NAME];

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
     * @return \stdClass
     * @throws \Parsisolution\Gateway\Exceptions\InvalidRequestException
     * @throws \Parsisolution\Gateway\Exceptions\InvalidStateException
     * @throws \Parsisolution\Gateway\Exceptions\NotFoundTransactionException
     * @throws \Parsisolution\Gateway\Exceptions\RetryException
     */
    public function transactionFromSettleRequest($stateless = false)
    {
        $request = app()['request'];
        $parameters = [];

        if (! $stateless) {
            if ($this->hasInvalidState($stateless)) {
                throw new InvalidStateException;
            }

            $all = $request->session()->all();
            $to_forgets = [];
            foreach ($all as $key => $value) {
                if (substr($key, 0, 8) === 'gateway_') {
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

        $db = app()['db'];
        $transaction = $db->table($this->getTable())->where('id', $id)->first();

        if (! $transaction) {
            throw new NotFoundTransactionException;
        }

        if (in_array($transaction->status, [Transaction::STATE_SUCCEEDED, Transaction::STATE_FAILED])) {
            throw new RetryException;
        }

        return $transaction;
    }

    /**
     * retrieve respective driver instance from request
     *
     * @param bool $stateless
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws \Parsisolution\Gateway\Exceptions\InvalidRequestException
     * @throws \Parsisolution\Gateway\Exceptions\NotFoundTransactionException
     * @throws \Parsisolution\Gateway\Exceptions\RetryException
     * @throws \Parsisolution\Gateway\Exceptions\InvalidStateException
     */
    public function fromSettleRequest($stateless = false)
    {
        $transaction = $this->transactionFromSettleRequest($stateless);

        $driver = $this->of(strtoupper($transaction->provider));
        $driver->setTransactionId($transaction->id);

        return $driver;
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
        $driver = $this->fromSettleRequest($stateless);
        if ($stateless) {
            $driver->stateless();
        }

        return $driver->settle();
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createMellatDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.mellat'];

        return $this->buildProvider(Mellat::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createSadadDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.sadad'];

        return $this->buildProvider(Sadad::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createSamanDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.saman'];

        return $this->buildProvider(Saman::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createParsianDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.parsian'];

        return $this->buildProvider(Parsian::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createMabnaDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.mabna'];

        return $this->buildProvider(Mabna::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createMabnaOldDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.mabna-old'];

        return $this->buildProvider(MabnaOld::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createIrankishDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.irankish'];

        return $this->buildProvider(Irankish::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createAsanpardakhtDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.asanpardakht'];

        return $this->buildProvider(Asanpardakht::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createPayirDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.payir'];

        return $this->buildProvider(Payir::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createPardanoDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.pardano'];

        return $this->buildProvider(Pardano::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createZarinpalDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.zarinpal'];

        return $this->buildProvider(Zarinpal::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createNextpayDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.nextpay'];

        return $this->buildProvider(NextPay::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createJibitDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.jibit'];

        return $this->buildProvider(JiBit::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createSabapayDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.sabapay'];

        return $this->buildProvider(SabaPay::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createSizpayDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.sizpay'];

        return $this->buildProvider(Sizpay::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    protected function createPaypingDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.payping'];

        return $this->buildProvider(PayPing::class, $config);
    }

    /**
     * Get transactions table name
     *
     * @return string
     */
    private function getTable()
    {
        return Arr::get(app()['config'], self::CONFIG_FILE_NAME.'.table', 'gateway_transactions');
    }

    /**
     * Build a Gateway provider instance.
     *
     * @param  string $provider
     * @param  array $config
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    public function buildProvider($provider, $config)
    {
        return new $provider(app(), $this->formatConfig($config));
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
                'soap' => Arr::get(app()['config'], 'soap', []),
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
            ? app()['url']->to($redirect)
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

        $state = app()['request']->session()->pull('gateway__state');

        return ! (strlen($state) > 0 && app()['request']->input('_state') === $state);
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
