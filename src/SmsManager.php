<?php

declare(strict_types=1);

namespace AliYavari\IranSms;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Contracts\Sms;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * @internal Behind the SMS facade
 */
final class SmsManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('iran-sms.default');
    }

    /**
     * Get an SMS instance to send by specific SMS provider
     *
     * @throws InvalidArgumentException
     */
    public function provider(?string $provider = null): Sms
    {
        return $this->driver($provider);
    }

    /**
     * Set custom driver instance for the given driver key
     */
    public function setDriver(string $key, Driver $driver): self
    {
        $this->drivers[$key] = $driver;

        return $this;
    }
}
