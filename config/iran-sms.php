<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider (Driver)
    |--------------------------------------------------------------------------
    |
    | This option controls the default SMS provider that is used to send all SMS
    | messages unless another SMS provider is explicitly specified when sending
    | the message.
    |
    */
    'default' => env('SMS_PROVIDER', ''),

    /*
    |--------------------------------------------------------------------------
    | SMS Providers Configurations
    |--------------------------------------------------------------------------
    |
    | Here are the configurations of all of the SMS Providers available in this
    | package plus their respective settings.
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
