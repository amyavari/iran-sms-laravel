<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit;

use AliYavari\IranSms\Drivers\FarazSmsDriver;
use AliYavari\IranSms\Drivers\KavenegarDriver;
use AliYavari\IranSms\Drivers\MeliPayamakDriver;
use AliYavari\IranSms\Drivers\PayamResanDriver;
use AliYavari\IranSms\Drivers\SmsIrDriver;
use AliYavari\IranSms\SmsManager;
use AliYavari\IranSms\Tests\Fixtures\ConcreteTestDriver;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

final class SmsManagerTest extends TestCase
{
    #[Test]
    public function it_returns_default_sms_driver_from_config(): void
    {
        Config::set('iran-sms.default', 'test');

        $this->assertSame('test', $this->smsManager()->getDefaultDriver());
    }

    #[Test]
    public function it_calls_specified_provider(): void
    {
        $this->expectExceptionMessage('Driver [test_driver] not supported.');

        $this->smsManager()->provider('test_driver');
    }

    #[Test]
    public function it_sets_custom_instance_as_driver_instance(): void
    {
        $testDriver = new ConcreteTestDriver('123456', true);

        $this->smsManager()->setDriver('test_driver', $testDriver);

        $retrievedDriver = $this->smsManager()->driver('test_driver');

        $this->assertInstanceOf(ConcreteTestDriver::class, $retrievedDriver);
        $this->assertSame('123456', $this->callProtectedMethod($retrievedDriver, 'getDefaultSender'));
    }

    #[Test]
    public function it_returns_sms_ir_instance(): void
    {
        $sms = $this->smsManager()->provider('sms_ir');

        $this->assertInstanceOf(SmsIrDriver::class, $sms);
    }

    #[Test]
    public function it_returns_meli_payamak_instance(): void
    {
        $sms = $this->smsManager()->provider('meli_payamak');

        $this->assertInstanceOf(MeliPayamakDriver::class, $sms);
    }

    #[Test]
    public function it_returns_payam_resan_instance(): void
    {
        $sms = $this->smsManager()->provider('payam_resan');

        $this->assertInstanceOf(PayamResanDriver::class, $sms);
    }

    #[Test]
    public function it_returns_kavenegar_instance(): void
    {
        $sms = $this->smsManager()->provider('kavenegar');

        $this->assertInstanceOf(KavenegarDriver::class, $sms);
    }

    #[Test]
    public function it_returns_faraz_sms_instance(): void
    {
        $sms = $this->smsManager()->provider('faraz_sms');

        $this->assertInstanceOf(FarazSmsDriver::class, $sms);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function smsManager(): SmsManager
    {
        return $this->app->make(SmsManager::class);
    }
}
