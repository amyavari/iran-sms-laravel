<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit;

use AliYavari\IranSms\Drivers\AmootSmsDriver;
use AliYavari\IranSms\Drivers\AsanakDriver;
use AliYavari\IranSms\Drivers\BehinPayamDriver;
use AliYavari\IranSms\Drivers\FakeDriver;
use AliYavari\IranSms\Drivers\FaraPayamakDriver;
use AliYavari\IranSms\Drivers\FarazSmsDriver;
use AliYavari\IranSms\Drivers\GhasedakDriver;
use AliYavari\IranSms\Drivers\KavenegarDriver;
use AliYavari\IranSms\Drivers\LimoSmsDriver;
use AliYavari\IranSms\Drivers\MeliPayamakDriver;
use AliYavari\IranSms\Drivers\PayamResanDriver;
use AliYavari\IranSms\Drivers\RayganSmsDriver;
use AliYavari\IranSms\Drivers\SaharSmsDriver;
use AliYavari\IranSms\Drivers\SmsIrDriver;
use AliYavari\IranSms\Drivers\WebOneDriver;
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
    public function it_sets_custom_fake_driver_instance_as_driver_instance(): void
    {
        $testDriver = $this->mock(FakeDriver::class);

        $this->smsManager()->setDriver('test_driver', $testDriver);

        $retrievedDriver = $this->smsManager()->driver('test_driver');

        $this->assertSame($testDriver, $retrievedDriver);
    }

    #[Test]
    public function it_always_returns_new_instance_of_sms_driver_class_for_immutability(): void
    {
        $this->smsManager()->extend('test_driver', fn () => new ConcreteTestDriver('123456', true));

        $instanceOne = $this->smsManager()->driver('test_driver');
        $instanceTwo = $this->smsManager()->driver('test_driver');

        $this->assertNotSame($instanceOne, $instanceTwo);
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

    #[Test]
    public function it_returns_raygan_sms_instance(): void
    {
        $sms = $this->smsManager()->provider('raygan_sms');

        $this->assertInstanceOf(RayganSmsDriver::class, $sms);
    }

    #[Test]
    public function it_returns_web_one_instance(): void
    {
        $sms = $this->smsManager()->provider('web_one');

        $this->assertInstanceOf(WebOneDriver::class, $sms);
    }

    #[Test]
    public function it_returns_amoot_sms_instance(): void
    {
        $sms = $this->smsManager()->provider('amoot_sms');

        $this->assertInstanceOf(AmootSmsDriver::class, $sms);
    }

    #[Test]
    public function it_returns_fara_payamak_instance(): void
    {
        $sms = $this->smsManager()->provider('fara_payamak');

        $this->assertInstanceOf(FaraPayamakDriver::class, $sms);
    }

    #[Test]
    public function it_returns_ghasedak_instance(): void
    {
        $sms = $this->smsManager()->provider('ghasedak');

        $this->assertInstanceOf(GhasedakDriver::class, $sms);
    }

    #[Test]
    public function it_returns_limo_sms_instance(): void
    {
        $sms = $this->smsManager()->provider('limo_sms');

        $this->assertInstanceOf(LimoSmsDriver::class, $sms);
    }

    #[Test]
    public function it_returns_behin_payam_instance(): void
    {
        $sms = $this->smsManager()->provider('behin_payam');

        $this->assertInstanceOf(BehinPayamDriver::class, $sms);
    }

    #[Test]
    public function it_returns_asanak_instance(): void
    {
        $sms = $this->smsManager()->provider('asanak');

        $this->assertInstanceOf(AsanakDriver::class, $sms);
    }

    #[Test]
    public function it_returns_sahar_sms_instance(): void
    {
        $sms = $this->smsManager()->provider('sahar_sms');

        $this->assertInstanceOf(SaharSmsDriver::class, $sms);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function smsManager(): SmsManager
    {
        return $this->app->make(SmsManager::class);
    }
}
