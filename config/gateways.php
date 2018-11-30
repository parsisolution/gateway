<?php

return [

    //-------------------------------
    // Tables names
    //--------------------------------
    'table' => 'gateway_transactions',

    //--------------------------------
    // Soap configuration
    //--------------------------------
    'soap' => [
        'attempts' => 2 // Attempts if soap connection is fail
    ],

    //--------------------------------
    // Mabna gateway
    //--------------------------------
    'mabna' => [
        'name' => '',
        'active' => false,
        'order' => 1,
        'terminalId' => 00000000,
        'callback-url' => '/'
    ],

    //--------------------------------
    // Mellat gateway
    //--------------------------------
    'mellat' => [
        'name' => '',
        'active' => false,
        'order' => 2,
        'username' => '',
        'password' => '',
        'terminalId' => 0000000,
        'callback-url' => '/'
    ],

    //--------------------------------
    // Sadad gateway
    //--------------------------------
    'sadad' => [
        'name' => '',
        'active' => false,
        'order' => 3,
        'merchant' => '',
        'transactionKey' => '',
        'terminalId' => 000000000,
        'callback-url' => '/'
    ],

    //--------------------------------
    // Saman gateway
    //--------------------------------
    'saman' => [
        'name' => '',
        'active' => false,
        'order' => 4,
        'merchant' => '',
        'password' => '',
        'callback-url' => '/',
    ],

    //--------------------------------
    // Parsian gateway
    //--------------------------------
    'parsian' => [
        'name' => '',
        'active' => false,
        'order' => 5,
        'pin' => 'xxxxxxxxxxxxxxxxxxxx',
        'callback-url' => '/'
    ],

    //--------------------------------
    // Saderat gateway
    //--------------------------------
    'saderat' => [
        'name' => '',
        'active' => false,
        'order' => 6,
        'merchant-id' => '999999999999999',
        'terminal-id' => '99999999',
        'public-key' => storage_path('gateways/saderat/saderat-public-key.pem'),
        'private-key' => storage_path('gateways/saderat/saderat-private-key.pem'),
        'callback-url' => '/'
    ],

    //--------------------------------
    // IranKish gateway
    //--------------------------------
    'irankish' => [
        'name' => '',
        'active' => false,
        'order' => 7,
        'merchant-id' => 'xxxx',
        'sha1-key' => 'xxxxxxxxxxxxxxxxxxxx',
        'description' => 'description',
        'callback-url' => '/'
    ],

    //--------------------------------
    // Asan Pardakht gateway
    //--------------------------------
    'asanpardakht' => [
        'name' => '',
        'active' => false,
        'order' => 8,
        'merchantId' => '',
        'merchantConfigId' => '',
        'username' => '',
        'password' => '',
        'key' => '',
        'iv' => '',
        'callback-url' => '/',
    ],

    //--------------------------------
    // PayIr gateway
    //--------------------------------
    'payir' => [
        'name' => '',
        'active' => false,
        'order' => 9,
        'api' => env('PAY_IR_API_KEY'),
        'callback-url' => '/'
    ],

    //--------------------------------
    // Pardano gateway
    //--------------------------------
    'pardano' => [
        'name' => '',
        'active' => false,
        'order' => 10,
        'api' => env('PARDANO_API_KEY', 'test'), // use test or your api key
        'callback-url' => '/'
    ],

    //--------------------------------
    // Zarinpal gateway
    //--------------------------------
    'zarinpal' => [
        'name' => '',
        'active' => false,
        'order' => 11,
        'merchant-id' => env('ZARINPAL_API_KEY', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
        'type' => 'normal',             // Types: [zarin-gate || normal || zarin-gate-sad || zarin-gate-sep]
        'server' => 'test',                // Servers: [germany || iran || test]
        'description' => 'description',
        'callback-url' => '/',
    ],

    //--------------------------------
    // NextPay gateway
    //--------------------------------
    'nextpay' => [
        'name' => '',
        'active' => false,
        'order' => 12,
        'api' => env('NEXTPAY_API_KEY', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'),
        'callback-url' => '/'
    ],

    // JiBit gateway
    //--------------------------------
    'jibit' => [
        'name' => '',
        'active' => false,
        'order' => 13,
        'merchant-id' => 'xxxx',
        'password' => env('JIBIT_PASS'),
        'callback-url' => '/',
        'user-mobile' => '09xxxxxxxxx'
    ],

    //--------------------------------
    // SabaPay gateway
    //--------------------------------
    'sabapay' => [
        'name' => '',
        'active' => false,
        'order' => 14,
        'api' => env('SABAPAY_API_KEY'),
        'callback-url' => '/'
    ],
];
