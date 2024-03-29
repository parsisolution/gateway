<?php

return [

    //-------------------------------
    // Tables names
    //--------------------------------
    'table' => 'gateway_transactions',

    //-------------------------------
    // Cache prefix
    //--------------------------------
    'cache_prefix' => 'gateway_transactions_',

    //--------------------------------
    // Soap configuration
    //--------------------------------
    'soap' => [
        'attempts' => 2, // Attempts if soap connection fails
    ],

    //--------------------------------
    // Mellat gateway
    //--------------------------------
    'mellat' => [
        'name'         => 'ملت',
        'active'       => false,
        'order'        => 1,
        'username'     => env('MELLAT_USERNAME'),
        'password'     => env('MELLAT_PASSWORD'),
        'terminal-id'  => env('MELLAT_TERMINAL_ID'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Saman gateway
    //--------------------------------
    'saman' => [
        'name'         => 'سامان',
        'active'       => false,
        'order'        => 2,
        'terminal-id'  => env('SAMAN_TERMINAL_ID'),
        'username'     => env('SAMAN_USERNAME'),
        'password'     => env('SAMAN_PASSWORD'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Sadad gateway
    //--------------------------------
    'sadad' => [
        'name'         => 'ملی',
        'active'       => false,
        'order'        => 3,
        'merchant-id'  => env('SADAD_MERCHANT_ID'),
        'terminal-id'  => env('SADAD_TERMINAL_ID'),
        'terminal-key' => env('SADAD_TERMINAL_KEY'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Parsian gateway
    //--------------------------------
    'parsian' => [
        'name'          => 'پارسیان',
        'active'        => false,
        'order'         => 4,
        'login-account' => env('PARSIAN_LOGIN_ACCOUNT'),
        'callback-url'  => '/',
    ],

    //--------------------------------
    // Pasargad gateway
    //--------------------------------
    'pasargad' => [
        'name'             => 'پاسارگاد',
        'active'           => false,
        'order'            => 5,
        'merchant-code'    => env('PASARGAD_MERCHANT_CODE'),
        'terminal-code'    => env('PASARGAD_TERMINAL_CODE'),
        'ssl-verification' => true,
        'redirect-method'  => 'Get',  // Methods: [Get || Post] \Parsisolution\Gateway\RedirectResponse::TYPE_GET
        'rsa-key-type'     => 'file', // Types:   [File || String]
        'rsa-key'          => storage_path('gateways/pasargad/certificate.xml'),
        'callback-url'     => '/',
    ],

    //--------------------------------
    // Novin gateway
    //--------------------------------
    'novin' => [
        'name'                 => 'پرداخت نوین',
        'active'               => false,
        'order'                => 6,
        'username'             => env('NOVIN_USERNAME'),
        'password'             => env('NOVIN_PASSWORD'),
        'merchant-id'          => env('NOVIN_MERCHANT_ID'), // optional
        'terminal-id'          => env('NOVIN_TERMINAL_ID'), // required only if the merchant has more than one terminal
        'no-sign-mode'         => false,
        'auto-login'           => true,
        'certificate-path'     => storage_path('gateways/novin/certificate.pem'),
        'certificate-password' => env('NOVIN_CERTIFICATE_PASSWORD'),
        'temp-files-dir'       => storage_path('gateways/novin/temp'),
        'api-type'             => 'SOAP', // Types: [SOAP || REST] \Parsisolution\Gateway\ApiType::SOAP
        'callback-url'         => '/',
    ],

    //--------------------------------
    // IranKish gateway
    //--------------------------------
    'irankish' => [
        'name'         => 'ایران کیش',
        'active'       => false,
        'order'        => 7,
        'acceptor-id'  => env('IRANKISH_ACCEPTOR_ID'),
        'terminal-id'  => env('IRANKISH_TERMINAL_ID'),
        'password'     => env('IRANKISH_PASSWORD'),
        'public-key'   => env('IRANKISH_PUBLIC_KEY'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Sepehr gateway
    //--------------------------------
    'sepehr' => [
        'name'         => 'سپهر',
        'active'       => false,
        'order'        => 8,
        'terminal-id'  => env('SEPEHR_TERMINAL_ID'),
        'get-method'   => false,
        'api-type'     => 'REST', // Types: [SOAP || REST] \Parsisolution\Gateway\ApiType::REST
        'callback-url' => '/',
    ],

    //--------------------------------
    // Asan Pardakht gateway
    //--------------------------------
    'asanpardakht' => [
        'name'               => 'آپ',
        'active'             => false,
        'order'              => 9,
        'merchant-config-id' => env('ASANPARDAKHT_MERCHANT_CONFIG_ID'),
        'username'           => env('ASANPARDAKHT_USERNAME'),
        'password'           => env('ASANPARDAKHT_PASSWORD'),
        'key'                => env('ASANPARDAKHT_KEY'),
        'iv'                 => env('ASANPARDAKHT_IV'),
        'api-type'           => 'SOAP', // Types: [SOAP || REST] \Parsisolution\Gateway\ApiType::SOAP
        'callback-url'       => '/',
    ],

    //--------------------------------
    // Fanava gateway
    //--------------------------------
    'fanava' => [
        'name'         => 'فن آوا کارت',
        'active'       => false,
        'order'        => 10,
        'terminal-id'  => env('FANAVA_TERMINAL_ID'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Vandar gateway
    //--------------------------------
    'vandar' => [
        'name'         => 'وندار',
        'active'       => false,
        'order'        => 20,
        'api-key'      => env('VANDAR_API_KEY'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // PayIr gateway
    //--------------------------------
    'payir' => [
        'name'         => 'شبکه پرداخت پی',
        'active'       => false,
        'order'        => 21,
        'api-key'      => env('PAY_IR_API_KEY', 'test'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Zarinpal gateway
    //--------------------------------
    'zarinpal' => [
        'name'         => 'زرین پال',
        'active'       => false,
        'order'        => 22,
        'merchant-id'  => env('ZARINPAL_MERCHANT_ID', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
        'type'         => 'normal', // Types:   [zarin-gate || normal || zarin-gate-sad || zarin-gate-sep]
        'server'       => 'iran',   // Servers: [germany || iran || test]
        'callback-url' => '/',
    ],

    //--------------------------------
    // JiBit gateway
    //--------------------------------
    'jibit' => [
        'name'         => 'جیبیت',
        'active'       => false,
        'order'        => 23,
        'api-key'      => env('JIBIT_API_KEY'),
        'api-secret'   => env('JIBIT_API_SECRET'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // PayPing gateway
    //--------------------------------
    'payping' => [
        'name'         => 'پی پینگ',
        'active'       => false,
        'order'        => 24,
        'api-key'      => env('PAYPING_API_KEY'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // IDPay gateway
    //--------------------------------
    'idpay' => [
        'name'         => 'آیدی پی',
        'active'       => false,
        'order'        => 25,
        'api-key'      => env('IDPAY_API_KEY', '6a7f99eb-7c20-4412-a972-6dfb7cd253a4'),
        'sandbox'      => false,
        'callback-url' => '/',
    ],

    //--------------------------------
    // NextPay gateway
    //--------------------------------
    'nextpay' => [
        'name'         => 'نکست پی',
        'active'       => false,
        'order'        => 26,
        'api-key'      => env('NEXTPAY_API_KEY', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
        'api-type'     => 'REST', // Types: [SOAP || REST] \Parsisolution\Gateway\ApiType::REST
        'callback-url' => '/',
        'settings'     => [
            'soap' => [
                'attempts' => 2, // Attempts if soap connection fails
                'options'  => [
                    'cache_wsdl'     => 0,
                    'encoding'       => 'UTF-8',
                    'trace'          => 1,
                    'stream_context' => stream_context_create([
                        'ssl' => [
                            'verify_peer'       => false,
                            'verify_peer_name'  => false,
                            'allow_self_signed' => true,
                        ],
                    ]),
                ],
            ],
        ],
    ],

    //--------------------------------
    // Sizpay gateway
    //--------------------------------
    'sizpay' => [
        'name'         => 'سیزپی',
        'active'       => false,
        'order'        => 27,
        'merchant-id'  => env('SIZPAY_MERCHANT_ID'),
        'terminal-id'  => env('SIZPAY_TERMINAL_ID'),
        'username'     => env('SIZPAY_USERNAME'),
        'password'     => env('SIZPAY_PASSWORD'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // IranDargah gateway
    //--------------------------------
    'irandargah' => [
        'name'         => 'ایران درگاه',
        'active'       => false,
        'order'        => 28,
        'merchant-id'  => env('IRANDARGAH_MERCHANT_ID'),
        'sandbox'      => false,
        'callback-url' => '/',
    ],

    //--------------------------------
    // SabaPay gateway
    //--------------------------------
    'sabapay' => [
        'name'         => 'صبا پی',
        'active'       => false,
        'order'        => 29,
        'api-key'      => env('SABAPAY_API_KEY'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Shepa gateway
    //--------------------------------
    'shepa' => [
        'name'         => 'شپا (شبکه پرداخت آنلاین)',
        'active'       => false,
        'order'        => 30,
        'api-key'      => env('SHEPA_API_KEY', 'sandbox'), // use sandbox for test
        'sandbox'      => false,
        'callback-url' => '/',
    ],

    //--------------------------------
    // Zibal gateway
    //--------------------------------
    'zibal' => [
        'name'         => 'زیبال',
        'active'       => false,
        'order'        => 31,
        'merchant'     => env('ZIBAL_MERCHANT', 'zibal'), // use zibal for test
        'callback-url' => '/',
    ],

    //--------------------------------
    // Jibimo gateway
    //--------------------------------
    'jibimo' => [
        'name'         => 'جیبی‌مو',
        'active'       => false,
        'order'        => 32,
        'api-key'      => env('JIBIMO_API_KEY'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // AqayePardakht gateway
    //--------------------------------
    'aqayepardakht' => [
        'name'         => 'آقای پرداخت',
        'active'       => false,
        'order'        => 33,
        'pin'          => env('AQAYEPARDAKHT_PIN', 'sandbox'), // use sandbox for test
        'callback-url' => '/',
    ],

    //--------------------------------
    // Bahamta gateway
    //--------------------------------
    'bahamta' => [
        'name'         => 'باهمتا',
        'active'       => false,
        'order'        => 34,
        'api-key'      => env('BAHAMTA_API_KEY'),
        'sandbox'      => false,
        'callback-url' => '/',
    ],

    //--------------------------------
    // ParsPal gateway
    //--------------------------------
    'parspal' => [
        'name'         => 'پارس پال',
        'active'       => false,
        'order'        => 35,
        'api-key'      => env('PARSPAL_API_KEY'),
        'sandbox'      => false,
        'callback-url' => '/',
    ],

    //--------------------------------
    // BitPay gateway
    //--------------------------------
    'bitpay' => [
        'name'         => 'بیت پی',
        'active'       => false,
        'order'        => 36,
        'api-key'      => env('BITPAY_API_KEY', 'adxcv-zzadq-polkjsad-opp13opoz-1sdf455aadzmck1244567'),
        'sandbox'      => false,
        'callback-url' => '/',
    ],

    //--------------------------------
    // Milyoona gateway
    //--------------------------------
    'milyoona' => [
        'name'         => 'میلیونا',
        'active'       => false,
        'order'        => 37,
        'terminal-id'  => env('MILYOONA_TERMINAL', 'milyoona'), // use milyoona for test
        'callback-url' => '/',
    ],

    //--------------------------------
    // Sepal gateway
    //--------------------------------
    'sepal' => [
        'name'         => 'سپال',
        'active'       => false,
        'order'        => 38,
        'api-key'      => env('SEPAL_API_KEY', 'test'), // use "test" for test
        'sandbox'      => false,
        'callback-url' => '/',
    ],

    //--------------------------------
    // TiPoul gateway
    //--------------------------------
    'tipoul' => [
        'name'   => 'تیپول',
        'active' => false,
        'order'  => 39,
        // use 66b3cebc-b125-4c73-90d3-d9ace2a68b44 for test
        'token'           => env('TIPOUL_TOKEN', '66b3cebc-b125-4c73-90d3-d9ace2a68b44'),
        'redirect-method' => 'Get',  // Methods: [Get || Post] \Parsisolution\Gateway\RedirectResponse::TYPE_GET
        'callback-url'    => '/',
    ],

    //--------------------------------
    // DigiPay gateway
    //--------------------------------
    'digipay' => [
        'name'          => 'دیجی‌پی',
        'active'        => false,
        'order'         => 40,
        'type'          => env('DIGIPAY_TYPE', 'UPG'), // Types: [UPG || IPG || WPG]
        'username'      => env('DIGIPAY_USERNAME'),
        'password'      => env('DIGIPAY_PASSWORD'),
        'client-id'     => env('DIGIPAY_CLIENT_ID'),
        'client-secret' => env('DIGIPAY_CLIENT_SECRET'),
        'sandbox'       => false,
        'callback-url'  => '/',
    ],

    //--------------------------------
    // YekPay gateway
    //--------------------------------
    'yekpay' => [
        'name'         => 'یک‌پی',
        'active'       => false,
        'order'        => 50,
        'merchant-id'  => env('YEKPAY_MERCHANT_ID'),
        'sandbox'      => false,
        'callback-url' => '/',
    ],
];
