<?php

declare(strict_types=1);

namespace AliYavari\IranSms;

use AliYavari\IranSms\Contracts\Sms;
use AliYavari\IranSms\Drivers\FakeDriver;
use Illuminate\Support\Manager;
use InvalidArgumentException;

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
     * Create an instance of fake SMS driver
     */
    public function createFakeDriver(): FakeDriver
    {
        return $this->container->make(FakeDriver::class);
    }
}
