<?php

namespace Parsisolution\Gateway;

use Illuminate\Support\Arr;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Parsisolution\Gateway\Exceptions\GatewayException;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\Exceptions\InvalidStateException;
use Parsisolution\Gateway\Exceptions\NullConfigException;
use Parsisolution\Gateway\Providers\AqayePardakht\AqayePardakht;
use Parsisolution\Gateway\Providers\AsanPardakht\AsanPardakht;
use Parsisolution\Gateway\Providers\Bahamta\Bahamta;
use Parsisolution\Gateway\Providers\BitPay\BitPay;
use Parsisolution\Gateway\Providers\DigiPay\DigiPay;
use Parsisolution\Gateway\Providers\Fanava\Fanava;
use Parsisolution\Gateway\Providers\IDPay\IDPay;
use Parsisolution\Gateway\Providers\IranDargah\IranDargah;
use Parsisolution\Gateway\Providers\Irankish\Irankish;
use Parsisolution\Gateway\Providers\Jibimo\Jibimo;
use Parsisolution\Gateway\Providers\JiBit\JiBit;
use Parsisolution\Gateway\Providers\Mellat\Mellat;
use Parsisolution\Gateway\Providers\Milyoona\Milyoona;
use Parsisolution\Gateway\Providers\NextPay\NextPay;
use Parsisolution\Gateway\Providers\Novin\Novin;
use Parsisolution\Gateway\Providers\Parsian\Parsian;
use Parsisolution\Gateway\Providers\ParsPal\ParsPal;
use Parsisolution\Gateway\Providers\Pasargad\Pasargad;
use Parsisolution\Gateway\Providers\Payir\Payir;
use Parsisolution\Gateway\Providers\PayPing\PayPing;
use Parsisolution\Gateway\Providers\SabaPay\SabaPay;
use Parsisolution\Gateway\Providers\Sadad\Sadad;
use Parsisolution\Gateway\Providers\Saman\Saman;
use Parsisolution\Gateway\Providers\Sepal\Sepal;
use Parsisolution\Gateway\Providers\Sepehr\Sepehr;
use Parsisolution\Gateway\Providers\Shepa\Shepa;
use Parsisolution\Gateway\Providers\Sizpay\Sizpay;
use Parsisolution\Gateway\Providers\TiPoul\TiPoul;
use Parsisolution\Gateway\Providers\Vandar\Vandar;
use Parsisolution\Gateway\Providers\YekPay\YekPay;
use Parsisolution\Gateway\Providers\Zarinpal\Zarinpal;
use Parsisolution\Gateway\Providers\Zibal\Zibal;

class GatewayManager extends Manager implements Contracts\Factory
{
    const CONFIG_FILE_NAME = 'gateways';

    const MELLAT = 1;

    const SAMAN = 2;

    const SADAD = 3;

    const PARSIAN = 4;

    const PASARGAD = 5;

    const NOVIN = 6;

    const IRANKISH = 7;

    const SEPEHR = 8;

    const ASANPARDAKHT = 9;

    const FANAVA = 10;

    const VANDAR = 20;

    const PAYIR = 21;

    const ZARINPAL = 22;

    const JIBIT = 23;

    const PAYPING = 24;

    const IDPAY = 25;

    const NEXTPAY = 26;

    const SIZPAY = 27;

    const IRANDARGAH = 28;

    const SABAPAY = 29;

    const SHEPA = 30;

    const ZIBAL = 31;

    const JIBIMO = 32;

    const AQAYEPARDAKHT = 33;

    const BAHAMTA = 34;

    const PARSPAL = 35;

    const BITPAY = 36;

    const MILYOONA = 37;

    const SEPAL = 38;

    const TIPOUL = 39;

    const DIGIPAY = 40;

    const YEKPAY = 50;

    private $available_drivers = [
        1  => 'MELLAT',
        2  => 'SAMAN',
        3  => 'SADAD',
        4  => 'PARSIAN',
        5  => 'PASARGAD',
        6  => 'NOVIN',
        7  => 'IRANKISH',
        8  => 'SEPEHR',
        9  => 'ASANPARDAKHT',
        10 => 'FANAVA',
        20 => 'VANDAR',
        21 => 'PAYIR',
        22 => 'ZARINPAL',
        23 => 'JIBIT',
        24 => 'PAYPING',
        25 => 'IDPAY',
        26 => 'NEXTPAY',
        27 => 'SIZPAY',
        28 => 'IRANDARGAH',
        29 => 'SABAPAY',
        30 => 'SHEPA',
        31 => 'ZIBAL',
        32 => 'JIBIMO',
        33 => 'AQAYEPARDAKHT',
        34 => 'BAHAMTA',
        35 => 'PARSPAL',
        36 => 'BITPAY',
        37 => 'MILYOONA',
        38 => 'SEPAL',
        39 => 'TIPOUL',
        40 => 'DIGIPAY',
        50 => 'YEKPAY',
    ];

    /**
     * Get all the available "drivers".
     *
     * @return array
     */
    public function availableDrivers()
    {
        return array_keys($this->available_drivers);
    }

    /**
     * Get name of driver from its id number if it is provided by package and return id otherwise
     *
     * @param  int  $id
     * @return string
     */
    public function getDriverName($id)
    {
        return $this->available_drivers[$id] ?? $id;
    }

    /**
     * Get all the active "drivers" with their names and in specified order.
     *
     * @param  string  $name_prefix
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
     * @param  string  $driver
     * @return \Parsisolution\Gateway\AbstractProvider
     */
    public function of($driver)
    {
        return $this->driver($driver);
    }

    /**
     * Register a custom driver creator Closure.
     */
    public function extendUsing(string $driver, int $id, string $provider): self
    {
        $callback = function () use ($driver, $id, $provider) {
            return new $provider(app(), $id, array_merge([
                'settings' => [
                    'soap' => config('gateways.soap'),
                ],
            ], config('gateways.'.$driver)));
        };
        $this->customCreators[$driver] = $callback;
        $this->available_drivers[$id] = $driver;

        return $this;
    }

    /**
     * retrieve respective transaction from request
     *
     * @param  bool  $stateless
     * @return \Parsisolution\Gateway\Transactions\AuthorizedTransaction
     *
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

                    $parameters[$name] = $value;
                    $to_forgets[] = $key;
                }
            }
            $request->session()->forget($to_forgets);
        } else {
            $parameters = $request->input();
        }

        if (! array_key_exists('_order_id', $parameters) && ! array_key_exists('iN', $parameters)) {
            throw new InvalidRequestException;
        }
        if (array_key_exists('_order_id', $parameters)) {
            $orderId = $parameters['_order_id'];
        } else {
            $orderId = $parameters['iN'];
        }

        $db = app()['db'];
        $transactionDao = new TransactionDao($db, $this->getTable());

        $authorizedTransaction = $transactionDao->find($orderId);

        return $authorizedTransaction;
    }

    /**
     * Verify and Settle the callback request and get the settled transaction instance.
     *
     * @param  bool  $stateless
     * @param  array  $fieldsToUpdateOnSuccess
     * @return \Parsisolution\Gateway\Transactions\SettledTransaction
     *
     * @throws \Parsisolution\Gateway\Exceptions\TransactionException
     * @throws \Parsisolution\Gateway\Exceptions\InvalidRequestException
     * @throws \Parsisolution\Gateway\Exceptions\NotFoundTransactionException
     * @throws \Parsisolution\Gateway\Exceptions\RetryException
     * @throws \Parsisolution\Gateway\Exceptions\InvalidStateException
     */
    public function settle($stateless = false, $fieldsToUpdateOnSuccess = [])
    {
        $authorizedTransaction = $this->transactionFromSettleRequest($stateless);

        $driver = $this->of($this->getDriverName($authorizedTransaction['provider']));
        if ($stateless) {
            $driver->stateless();
        }

        return $driver->settle($authorizedTransaction, $fieldsToUpdateOnSuccess);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createMellatDriver()
    {
        return $this->buildProvider(self::MELLAT, Mellat::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createSamanDriver()
    {
        return $this->buildProvider(self::SAMAN, Saman::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createSadadDriver()
    {
        return $this->buildProvider(self::SADAD, Sadad::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createParsianDriver()
    {
        return $this->buildProvider(self::PARSIAN, Parsian::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createPasargadDriver()
    {
        return $this->buildProvider(self::PASARGAD, Pasargad::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createNovinDriver()
    {
        return $this->buildProvider(self::NOVIN, Novin::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createIrankishDriver()
    {
        return $this->buildProvider(self::IRANKISH, Irankish::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createSepehrDriver()
    {
        return $this->buildProvider(self::SEPEHR, Sepehr::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createAsanpardakhtDriver()
    {
        return $this->buildProvider(self::ASANPARDAKHT, AsanPardakht::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createFanavaDriver()
    {
        return $this->buildProvider(self::FANAVA, Fanava::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createVandarDriver()
    {
        return $this->buildProvider(self::VANDAR, Vandar::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createPayirDriver()
    {
        return $this->buildProvider(self::PAYIR, Payir::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createZarinpalDriver()
    {
        return $this->buildProvider(self::ZARINPAL, Zarinpal::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createJibitDriver()
    {
        return $this->buildProvider(self::JIBIT, JiBit::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createPaypingDriver()
    {
        return $this->buildProvider(self::PAYPING, PayPing::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createIdpayDriver()
    {
        return $this->buildProvider(self::IDPAY, IDPay::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createNextpayDriver()
    {
        return $this->buildProvider(self::NEXTPAY, NextPay::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createSizpayDriver()
    {
        return $this->buildProvider(self::SIZPAY, Sizpay::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createIrandargahDriver()
    {
        return $this->buildProvider(self::IRANDARGAH, IranDargah::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createSabapayDriver()
    {
        return $this->buildProvider(self::SABAPAY, SabaPay::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createShepaDriver()
    {
        return $this->buildProvider(self::SHEPA, Shepa::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createZibalDriver()
    {
        return $this->buildProvider(self::ZIBAL, Zibal::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createJibimoDriver()
    {
        return $this->buildProvider(self::JIBIMO, Jibimo::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createAqayepardakhtDriver()
    {
        return $this->buildProvider(self::AQAYEPARDAKHT, AqayePardakht::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createBahamtaDriver()
    {
        return $this->buildProvider(self::BAHAMTA, Bahamta::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createParspalDriver()
    {
        return $this->buildProvider(self::PARSPAL, ParsPal::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createBitpayDriver()
    {
        return $this->buildProvider(self::BITPAY, BitPay::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createMilyoonaDriver()
    {
        return $this->buildProvider(self::MILYOONA, Milyoona::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createSepalDriver()
    {
        return $this->buildProvider(self::SEPAL, Sepal::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createTipoulDriver()
    {
        return $this->buildProvider(self::TIPOUL, TiPoul::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createDigipayDriver()
    {
        return $this->buildProvider(self::DIGIPAY, DigiPay::class);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     *
     * @throws GatewayException
     */
    protected function createYekpayDriver()
    {
        return $this->buildProvider(self::YEKPAY, YekPay::class);
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
     * @throws GatewayException
     */
    public function buildProvider(int $id, string $provider): AbstractProvider
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.'.strtolower($this->available_drivers[$id] ?? 'null')];

        if (! $config) {
            throw new NullConfigException();
        }

        return new $provider(app(), $id, $this->formatConfig($config));
    }

    /**
     * Format the server configuration.
     *
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
     * @param  bool  $stateless
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
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function getDefaultDriver()
    {
        throw new InvalidArgumentException('No Gateway driver was specified.');
    }
}
