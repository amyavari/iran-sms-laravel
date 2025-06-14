<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Facades;

use AliYavari\IranSms\SmsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AliYavari\IranSms\Contracts\Sms driver(string $name = null)
 */
final class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsManager::class;
    }
}
