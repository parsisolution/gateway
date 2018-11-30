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
        'attempts' => 2 // Attempts if soap connection is fail
    ],

    //--------------------------------
    // Mellat gateway
    //--------------------------------
    'mellat'       => [
        'name'         => 'ملت',
        'active'       => false,
        'order'        => 1,
        'username'     => '',
        'password'     => '',
        'terminalId'   => 0000000,
        'callback-url' => '/',
    ],

    //--------------------------------
    // Sadad gateway
    //--------------------------------
    'sadad'        => [
        'name'           => 'ملی',
        'active'         => false,
        'order'          => 2,
        'merchant'       => '',
        'transactionKey' => '',
        'terminalId'     => 000000000,
        'callback-url'   => '/',
    ],

    //--------------------------------
    // Saman gateway
    //--------------------------------
    'saman'        => [
        'name'         => 'سامان',
        'active'       => false,
        'order'        => 3,
        'merchant'     => '',
        'password'     => '',
        'callback-url' => '/',
    ],

    //--------------------------------
    // Parsian gateway
    //--------------------------------
    'parsian'      => [
        'name'         => 'پارسیان',
        'active'       => false,
        'order'        => 4,
        'pin'          => 'xxxxxxxxxxxxxxxxxxxx',
        'callback-url' => '/',
    ],

    //--------------------------------
    // Mabna gateway
    //--------------------------------
    'mabna'        => [
        'name'         => 'مبنا',
        'active'       => false,
        'order'        => 5,
        'terminalId'   => env('MABNA_TERMINAL_ID'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Mabna old gateway
    //--------------------------------
    'mabna-old'      => [
        'name'         => 'مبنا',
        'active'       => false,
        'order'        => 6,
        'merchant-id'  => '999999999999999',
        'terminal-id'  => env('MABNA_TERMINAL_ID'),
        'public-key'   => storage_path('gateways/mabna/mabna-public-key.pem'),
        'private-key'  => storage_path('gateways/mabna/mabna-private-key.pem'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // IranKish gateway
    //--------------------------------
    'irankish'     => [
        'name'         => 'ایران کیش',
        'active'       => false,
        'order'        => 7,
        'merchant-id'  => 'xxxx',
        'sha1-key'     => 'xxxxxxxxxxxxxxxxxxxx',
        'description'  => 'description',
        'callback-url' => '/',
    ],

    //--------------------------------
    // Asan Pardakht gateway
    //--------------------------------
    'asanpardakht' => [
        'name'             => 'آپ',
        'active'           => false,
        'order'            => 8,
        'merchantId'       => '',
        'merchantConfigId' => '',
        'username'         => '',
        'password'         => '',
        'key'              => '',
        'iv'               => '',
        'callback-url'     => '/',
    ],

    //--------------------------------
    // PayIr gateway
    //--------------------------------
    'payir'        => [
        'name'         => 'شبکه پرداخت پی',
        'active'       => false,
        'order'        => 9,
        'api'          => env('PAY_IR_API_KEY'),
        'callback-url' => '/',
    ],

    //--------------------------------
    // Pardano gateway
    //--------------------------------
    'pardano'      => [
        'name'         => 'پردانو',
        'active'       => false,
        'order'        => 10,
        'api'          => env('PARDANO_API_KEY', 'test'), // use test or your api key
        'callback-url' => '/',
    ],

    //--------------------------------
    // Zarinpal gateway
    //--------------------------------
    'zarinpal'     => [
        'name'         => 'زرین پال',
        'active'       => false,
        'order'        => 11,
        'merchant-id'  => env('ZARINPAL_API_KEY', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
        'type'         => 'normal',             // Types: [zarin-gate || normal || zarin-gate-sad || zarin-gate-sep]
        'server'       => 'test',                // Servers: [germany || iran || test]
        'description'  => 'description',
        'callback-url' => '/',
    ],

    //--------------------------------
    // NextPay gateway
    //--------------------------------
    'nextpay'      => [
        'name'         => 'نکست پی',
        'active'       => false,
        'order'        => 12,
        'api'          => env('NEXTPAY_API_KEY', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
        'callback-url' => '/',
    ],

    // JiBit gateway
    //--------------------------------
    'jibit'        => [
        'name'         => 'جیبیت',
        'active'       => false,
        'order'        => 13,
        'merchant-id'  => 'xxxx',
        'password'     => env('JIBIT_PASS'),
        'callback-url' => '/',
        'user-mobile'  => '09xxxxxxxxx',
    ],

    //--------------------------------
    // SabaPay gateway
    //--------------------------------
    'sabapay'      => [
        'name'         => 'صبا پی',
        'active'       => false,
        'order'        => 14,
        'api'          => env('SABAPAY_API_KEY'),
        'callback-url' => '/',
    ],
];
