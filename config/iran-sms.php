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

        // https://sms.ir/
        'sms_ir' => [
            'token' => env('SMS_TOKEN', ''),
            'from' => env('SMS_FROM', ''),
        ],

        // https://melipayamak.com/
        'meli_payamak' => [
            'username' => env('SMS_USERNAME', ''),
            'password' => env('SMS_PASSWORD', ''),
            'from' => env('SMS_FROM', ''),
        ],

        // https://payam-resan.com/
        'payam_resan' => [
            'token' => env('SMS_TOKEN', ''),
            'from' => env('SMS_FROM', ''),
        ],

        // https://kavenegar.com/
        'kavenegar' => [
            'token' => env('SMS_TOKEN', ''),
            'from' => env('SMS_FROM', ''),
        ],

        // https://farazsms.com/
        'faraz_sms' => [
            'token' => env('SMS_TOKEN', ''),
            'from' => env('SMS_FROM', ''),
        ],

        // https://raygansms.com/
        'raygan_sms' => [
            'token' => env('SMS_TOKEN', ''),
            'username' => env('SMS_USERNAME', ''),
            'password' => env('SMS_PASSWORD', ''),
            'from' => env('SMS_FROM', ''),
        ],

        // https://webone-sms.ir/
        'web_one' => [
            'token' => env('SMS_TOKEN', ''),
            'from' => env('SMS_FROM', ''),
        ],

        // https://www.amootsms.com/
        'amoot_sms' => [
            'token' => env('SMS_TOKEN', ''),
            'from' => env('SMS_FROM', ''),
        ],

        // https://farapayamak.ir/
        'fara_payamak' => [
            'username' => env('SMS_USERNAME', ''),
            'password' => env('SMS_PASSWORD', ''),
            'from' => env('SMS_FROM', ''),
        ],

        // https://ghasedak.me/
        'ghasedak' => [
            'token' => env('SMS_TOKEN', ''),
            'from' => env('SMS_FROM', ''),
        ],

        // https://limosms.com/
        'limo_sms' => [
            'token' => env('SMS_TOKEN', ''),
            'from' => env('SMS_FROM', ''),
        ],

        // https://behinpayam.com/
        'behin_payam' => [
            'token' => env('SMS_TOKEN', ''),
            'from' => env('SMS_FROM', ''),
        ],
    ],
];
