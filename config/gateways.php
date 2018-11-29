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
        'username'     => '',
        'password'     => '',
        'terminalId'   => 0000000,
        'callback-url' => '/'
    ],

    //--------------------------------
    // Sadad gateway
    //--------------------------------
    'sadad'        => [
        'merchant'       => '',
        'transactionKey' => '',
        'terminalId'     => 000000000,
        'callback-url'   => '/'
    ],

    //--------------------------------
    // Saman gateway
    //--------------------------------
    'saman'        => [
        'merchant'     => '',
        'password'     => '',
        'callback-url' => '/',
    ],

    //--------------------------------
    // Parsian gateway
    //--------------------------------
    'parsian'      => [
        'pin'          => 'xxxxxxxxxxxxxxxxxxxx',
        'callback-url' => '/'
    ],

    //--------------------------------
    // Saderat gateway
    //--------------------------------
    'saderat'      => [
        'merchant-id'  => '999999999999999',
        'terminal-id'  => '99999999',
        'public-key'   => storage_path('gateways/saderat/saderat-public-key.pem'),
        'private-key'  => storage_path('gateways/saderat/saderat-private-key.pem'),
        'callback-url' => '/'
    ],

    //--------------------------------
    // IranKish gateway
    //--------------------------------
    'irankish'     => [
        'merchant-id'  => 'xxxx',
        'sha1-key'     => 'xxxxxxxxxxxxxxxxxxxx',
        'description'  => 'description',
        'callback-url' => '/'
    ],

    //--------------------------------
    // Asan Pardakht gateway
    //--------------------------------
    'asanpardakht' => [
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
        'api'          => env('PAY_IR_API_KEY'),
        'callback-url' => '/'
    ],

    //--------------------------------
    // Pardano gateway
    //--------------------------------
    'pardano'      => [
        'api'          => env('PARDANO_API_KEY', 'test'), // use test or your api key
        'callback-url' => '/'
    ],

    //--------------------------------
    // Zarinpal gateway
    //--------------------------------
    'zarinpal'     => [
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
        'api'          => env('NEXTPAY_API_KEY', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
        'callback-url' => '/'
    ],

    // JiBit gateway
    //--------------------------------
    'jibit'        => [
        'merchant-id'  => 'xxxx',
        'password'     => env('JIBIT_PASS'),
        'callback-url' => '/',
        'user-mobile'  => '09xxxxxxxxx'
    ],

    //--------------------------------
    // SabaPay gateway
    //--------------------------------
    'sabapay'      => [
        'api'          => env('SABA_API_KEY', ''),
        'callback-url' => '/'
    ],
];
