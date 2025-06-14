<?php

declare(strict_types=1);

namespace AliYavari\IranSms;

use AliYavari\IranSms\Drivers\FakeDriver;
use Illuminate\Support\Manager;

final class SmsManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('iran-sms.default');
    }

    public function createFakeDriver(): FakeDriver
    {
        return $this->container->make(FakeDriver::class);
    }
}
