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
use Parsisolution\Gateway\Providers\Fanava\Fanava;
use Parsisolution\Gateway\Providers\IDPay\IDPay;
use Parsisolution\Gateway\Providers\IranDargah\IranDargah;
use Parsisolution\Gateway\Providers\Irankish\Irankish;
use Parsisolution\Gateway\Providers\Jibimo\Jibimo;
use Parsisolution\Gateway\Providers\JiBit\JiBit;
use Parsisolution\Gateway\Providers\Mellat\Mellat;
use Parsisolution\Gateway\Providers\NextPay\NextPay;
use Parsisolution\Gateway\Providers\Novin\Novin;
use Parsisolution\Gateway\Providers\Parsian\Parsian;
use Parsisolution\Gateway\Providers\Pasargad\Pasargad;
use Parsisolution\Gateway\Providers\Payir\Payir;
use Parsisolution\Gateway\Providers\PayPing\PayPing;
use Parsisolution\Gateway\Providers\SabaPay\SabaPay;
use Parsisolution\Gateway\Providers\Sadad\Sadad;
use Parsisolution\Gateway\Providers\Saman\Saman;
use Parsisolution\Gateway\Providers\Sepehr\Sepehr;
use Parsisolution\Gateway\Providers\Shepa\Shepa;
use Parsisolution\Gateway\Providers\Sizpay\Sizpay;
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
    const YEKPAY = 50;

    /**
     * Get all of the available "drivers".
     *
     * @return array
     */
    public static function availableDrivers()
    {
        return [
            self::MELLAT,
            self::SAMAN,
            self::SADAD,
            self::PARSIAN,
            self::PASARGAD,
            self::NOVIN,
            self::IRANKISH,
            self::SEPEHR,
            self::ASANPARDAKHT,
            self::FANAVA,
            self::VANDAR,
            self::PAYIR,
            self::ZARINPAL,
            self::JIBIT,
            self::PAYPING,
            self::IDPAY,
            self::NEXTPAY,
            self::SIZPAY,
            self::IRANDARGAH,
            self::SABAPAY,
            self::SHEPA,
            self::ZIBAL,
            self::JIBIMO,
            self::AQAYEPARDAKHT,
            self::YEKPAY,
        ];
    }

    /**
     * Get name of driver from its id number if it is provided by package and return id otherwise
     *
     * @param integer $id
     * @return string
     */
    public function getDriverName($id)
    {
        $map = [
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
            50 => 'YEKPAY',
        ];

        return $map[$id] ?? $id;
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
     * @return \Parsisolution\Gateway\Transactions\AuthorizedTransaction
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

        if (! key_exists('_order_id', $parameters) && ! key_exists('iN', $parameters)) {
            throw new InvalidRequestException;
        }
        if (key_exists('_order_id', $parameters)) {
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
     * @param bool $stateless
     * @param array $fieldsToUpdateOnSuccess
     *
     * @return \Parsisolution\Gateway\Transactions\SettledTransaction
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
     * @throws GatewayException
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
     * @throws GatewayException
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
     * @throws GatewayException
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
     * @throws GatewayException
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
     * @throws GatewayException
     */
    protected function createPasargadDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.pasargad'];

        return $this->buildProvider(Pasargad::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createNovinDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.novin'];

        return $this->buildProvider(Novin::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
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
     * @throws GatewayException
     */
    protected function createSepehrDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.sepehr'];

        return $this->buildProvider(Sepehr::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createAsanpardakhtDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.asanpardakht'];

        return $this->buildProvider(AsanPardakht::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createFanavaDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.fanava'];

        return $this->buildProvider(Fanava::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createVandarDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.vandar'];

        return $this->buildProvider(Vandar::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
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
     * @throws GatewayException
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
     * @throws GatewayException
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
     * @throws GatewayException
     */
    protected function createPaypingDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.payping'];

        return $this->buildProvider(PayPing::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createIdpayDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.idpay'];

        return $this->buildProvider(IDPay::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
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
     * @throws GatewayException
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
     * @throws GatewayException
     */
    protected function createIrandargahDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.irandargah'];

        return $this->buildProvider(IranDargah::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
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
     * @throws GatewayException
     */
    protected function createShepaDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.shepa'];

        return $this->buildProvider(Shepa::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createZibalDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.zibal'];

        return $this->buildProvider(Zibal::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createJibimoDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.jibimo'];

        return $this->buildProvider(Jibimo::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createAqayepardakhtDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.aqayepardakht'];

        return $this->buildProvider(AqayePardakht::class, $config);
    }

    /**
     * Create an instance of the specified driver.
     *
     * @return \Parsisolution\Gateway\AbstractProvider
     * @throws GatewayException
     */
    protected function createYekpayDriver()
    {
        $config = app()['config'][self::CONFIG_FILE_NAME.'.yekpay'];

        return $this->buildProvider(YekPay::class, $config);
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
     * @throws GatewayException
     */
    public function buildProvider($provider, $config)
    {
        if (! $config) {
            throw new NullConfigException();
        }

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
