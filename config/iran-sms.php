<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider (Driver)
    |--------------------------------------------------------------------------
    |
    | This option controls the default SMS provider that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a SMS provider inside the application.
    |
    */
    'default' => env('SMS_PROVIDER', ''),

    /*
    |--------------------------------------------------------------------------
    | SMS Providers
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the SMS "providers" for your application.
    |
    */
    'providers' => [

        'one' => [
            'username' => env('SMS_USERNAME', ''),
            'password' => env('SMS_PASSWORD', ''),
            'token' => env('SMS_TOKEN', ''),
            'from' => env('SMS_FROM', ''),
        ],

    ],
];
