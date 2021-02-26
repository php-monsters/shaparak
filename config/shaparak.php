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
        /*
        |--------------------------------------------------------------------------
        | Saman gateway configuration
        |--------------------------------------------------------------------------
        */
        'saman' => [
            'terminal_id' =>   env('SAMAN_TERMINAL_ID'),
            'terminal_pass' => env('SAMAN_TERMINAL_PASS'),
         ],
        /*
        |--------------------------------------------------------------------------
        | Parsian gateway configuration
        |--------------------------------------------------------------------------
        */
        'parsian' => [
            'pin' => env('PARSIAN_PIN', ''),
        ],
        /*
        |--------------------------------------------------------------------------
        | Pasargad gateway configuration
        |--------------------------------------------------------------------------
        */
        'pasargad' => [
            'terminal_id'       => env('PASARGAD_TERMINAL_ID'),
            'merchant_id'       => env('PASARGAD_MERCHANT_ID'),
            'certificate_path'  => env('PASARGAD_CERT_PATH', storage_path('shaparak/pasargad/certificate.xml')),
        ],
        /*
        |--------------------------------------------------------------------------
        | Mellat gateway configuration
        |--------------------------------------------------------------------------
        */
        'mellat'   => [
            'username'    => env('MELLAT_USERNAME'),
            'password'    => env('MELLAT_PASSWORD'),
            'terminal_id' => env('MELLAT_TERMINAL_ID'),
        ],
        /*
        |--------------------------------------------------------------------------
        | Melli/Sadad gateway configuration
        |--------------------------------------------------------------------------
        */
        'melli'    => [
            'merchant_id'     => env('MELLI_MERCHANT_ID'),
            'terminal_id'     => env('MELLI_TERMINAL_ID'),
            'transaction_key' => env('MELLI_TRANS_KEY'),
        ],
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
