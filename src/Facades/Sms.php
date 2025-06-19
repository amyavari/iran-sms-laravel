<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Facades;

use AliYavari\IranSms\SmsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AliYavari\IranSms\Contracts\Sms driver(string $driver = null)
 * @method static \AliYavari\IranSms\Contracts\Sms provider(string $provider = null)
 * @method static \AliYavari\IranSms\Contracts\Sms otp(string $phone, string $message)
 * @method static \AliYavari\IranSms\Contracts\Sms pattern(string|list<string> $phones, string $patternCode, array<string, mixed> $variables)
 * @method static \AliYavari\IranSms\Contracts\Sms text(string|list<string> $phones, string $message)
 * @method static \AliYavari\IranSms\Contracts\Sms from(string $from)
 */
final class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsManager::class;
    }
}
