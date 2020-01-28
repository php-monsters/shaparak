<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shaparak e-payment component`s operation mode
    |--------------------------------------------------------------------------
    |
    | *** very important config ***
    | please do not change it if you don't know what BankTest is
    |
    | production: component operates with real payments gateways
    | development/staging: component operates with simulated "Bank Test" (banktest.ir) gateways
    |
    */
    'mode' => env('SHAPARAK_MODE', 'production'),

    'log' => [
        'prefix' => 'SHAPARAK->',
    ],

    'providers' => [
        'saman' => [
            'terminal_id' => '',
            'terminal_pass' => '',
         ]
    ],

    /*
    |--------------------------------------------------------------------------
    | httpClient Options including soapClient/Guzzle/Curl
    |--------------------------------------------------------------------------
    |
    | options: Options
    |
    */
    'httpClientOptions' => [
        'soap'   => [],
        'curl'   => [],
    ],
];
