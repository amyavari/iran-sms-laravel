<?php

declare(strict_types=1);

namespace AliYavari\IranSms;

use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Contracts\Sms;
use AliYavari\IranSms\Drivers\FarazSmsDriver;
use AliYavari\IranSms\Drivers\KavenegarDriver;
use AliYavari\IranSms\Drivers\MeliPayamakDriver;
use AliYavari\IranSms\Drivers\PayamResanDriver;
use AliYavari\IranSms\Drivers\RayganSmsDriver;
use AliYavari\IranSms\Drivers\SmsIrDriver;
use AliYavari\IranSms\Drivers\WebOneDriver;
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

    protected function createSmsIrDriver(): SmsIrDriver
    {
        return $this->container->make(SmsIrDriver::class);
    }

    protected function createMeliPayamakDriver(): MeliPayamakDriver
    {
        return $this->container->make(MeliPayamakDriver::class);
    }

    protected function createPayamResanDriver(): PayamResanDriver
    {
        return $this->container->make(PayamResanDriver::class);
    }

    protected function createKavenegarDriver(): KavenegarDriver
    {
        return $this->container->make(KavenegarDriver::class);
    }

    protected function createFarazSmsDriver(): FarazSmsDriver
    {
        return $this->container->make(FarazSmsDriver::class);
    }

    protected function createRayganSmsDriver(): RayganSmsDriver
    {
        return $this->container->make(RayganSmsDriver::class);
    }

    protected function createWebOneDriver(): WebOneDriver
    {
        return $this->container->make(WebOneDriver::class);
    }
}
