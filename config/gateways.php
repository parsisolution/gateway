<?php

return [

    //-------------------------------
    // Tables names
    //--------------------------------
    'table'        => 'gateway_transactions',

    //--------------------------------
    // Soap configuration
    //--------------------------------
    'soap'         => [
        'attempts' => 2 // Attempts if soap connection fails
    ],

    //--------------------------------
    // Mellat gateway
    //--------------------------------
    'mellat'       => [
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
    'saman'        => [
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
    'sadad'        => [
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
    'parsian'      => [
        'name'          => 'پارسیان',
        'active'        => false,
        'order'         => 4,
        'login-account' => env('PARSIAN_LOGIN_ACCOUNT'),
        'callback-url'  => '/',
    ],

    //--------------------------------
    // IranKish gateway
    //--------------------------------
    'irankish'     => [
        'name'         => 'ایران کیش',
        'active'       => false,
        'order'        => 5,
        'acceptor-id'  => env('IRANKISH_ACCEPTOR_ID'),
        'terminal-id'  => env('IRANKISH_TERMINAL_ID'),
        'password'     => env('IRANKISH_PASSWORD'),
        'public-key'   => env('IRANKISH_PUBLIC_KEY'),
        'description'  => 'description',
        'callback-url' => '/',
    ],

    //--------------------------------
    // Mabna gateway
    //--------------------------------
    'mabna'        => [
        'name'         => 'مبنا',
        'active'       => false,
        'order'        => 6,
        'terminal-id'  => env('MABNA_TERMINAL_ID'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Mabna old gateway
    //--------------------------------
    'mabna-old'    => [
        'name'         => 'مبنا',
        'active'       => false,
        'order'        => 7,
        'merchant-id'  => env('MABNA_MERCHANT_ID'),
        'terminal-id'  => env('MABNA_TERMINAL_ID'),
        'public-key'   => storage_path('gateways/mabna/mabna-public-key.pem'),
        'private-key'  => storage_path('gateways/mabna/mabna-private-key.pem'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Asan Pardakht gateway
    //--------------------------------
    'asanpardakht' => [
        'name'               => 'آپ',
        'active'             => false,
        'order'              => 8,
        'merchant-config-id' => env('ASANPARDAKHT_MERCHANT_CONFIG_ID'),
        'username'           => env('ASANPARDAKHT_USERNAME'),
        'password'           => env('ASANPARDAKHT_PASSWORD'),
        'key'                => env('ASANPARDAKHT_KEY'),
        'iv'                 => env('ASANPARDAKHT_IV'),
        'api-type'           => 'SOAP', // \Parsisolution\Gateway\ApiType::SOAP
        'callback-url'       => '/',
    ],

    //--------------------------------
    // Vandar gateway
    //--------------------------------
    'vandar'       => [
        'name'         => 'وندار',
        'active'       => false,
        'order'        => 20,
        'api-key'      => env('VANDAR_API_KEY'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // PayIr gateway
    //--------------------------------
    'payir'        => [
        'name'         => 'شبکه پرداخت پی',
        'active'       => false,
        'order'        => 21,
        'api-key'      => env('PAY_IR_API_KEY', 'test'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Zarinpal gateway
    //--------------------------------
    'zarinpal'     => [
        'name'         => 'زرین پال',
        'active'       => false,
        'order'        => 22,
        'merchant-id'  => env('ZARINPAL_MERCHANT_ID', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
        'type'         => 'normal',             // Types: [zarin-gate || normal || zarin-gate-sad || zarin-gate-sep]
        'server'       => 'test',                // Servers: [germany || iran || test]
        'description'  => 'description',
        'callback-url' => '/',
    ],

    //--------------------------------
    // JiBit gateway
    //--------------------------------
    'jibit'        => [
        'name'         => 'جیبیت',
        'active'       => false,
        'order'        => 23,
        'merchant-id'  => env('MERCHANT_ID'),
        'password'     => env('JIBIT_PASSWORD'),
        'callback-url' => '/',
        'user-mobile'  => '09xxxxxxxxx',
    ],

    //--------------------------------
    // PayPing gateway
    //--------------------------------
    'payping'      => [
        'name'         => 'پی پینگ',
        'active'       => false,
        'order'        => 24,
        'api-key'      => env('PAYPING_API_KEY'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // IDPay gateway
    //--------------------------------
    'idpay'        => [
        'name'         => 'آیدی پی',
        'active'       => false,
        'order'        => 25,
        'api-key'      => env('IDPAY_API_KEY', '6a7f99eb-7c20-4412-a972-6dfb7cd253a4'),
        'sandbox'      => true,
        'callback-url' => '/',
    ],

    //--------------------------------
    // NextPay gateway
    //--------------------------------
    'nextpay'      => [
        'name'         => 'نکست پی',
        'active'       => false,
        'order'        => 26,
        'api-key'      => env('NEXTPAY_API_KEY', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
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
    'sizpay'       => [
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
    'irandargah'   => [
        'name'         => 'ایران درگاه',
        'active'       => false,
        'order'        => 28,
        'merchant-id'  => env('IRANDARGAH_MERCHANT_ID'),
        'server'       => 'test',                // Servers: [main || test]
        'description'  => 'description',
        'callback-url' => '/',
    ],

    //--------------------------------
    // SabaPay gateway
    //--------------------------------
    'sabapay'      => [
        'name'         => 'صبا پی',
        'active'       => false,
        'order'        => 29,
        'api-key'      => env('SABAPAY_API_KEY'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Pardano gateway
    //--------------------------------
    'pardano'      => [
        'name'         => 'پردانو',
        'active'       => false,
        'order'        => 30,
        'api-key'      => env('PARDANO_API_KEY', 'test'), // use test or your api key
        'callback-url' => '/',
    ],

    //--------------------------------
    // YekPay gateway
    //--------------------------------
    'yekpay'       => [
        'name'         => 'یک‌پی',
        'active'       => false,
        'order'        => 50,
        'merchant-id'  => env('YEKPAY_MERCHANT_ID'),
        'callback-url' => '/',
    ],
];
