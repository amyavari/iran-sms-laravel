<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit;

use AliYavari\IranSms\Drivers\FakeDriver;
use AliYavari\IranSms\Facades\Sms;
use AliYavari\IranSms\SmsManager;
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
    public function sms_facade_returns_instance_of_sms_manager_class(): void
    {
        $this->assertInstanceOf(SmsManager::class, Sms::getFacadeRoot());
    }

    #[Test]
    public function it_returns_fake_driver(): void
    {
        $this->assertInstanceOf(FakeDriver::class, $this->smsManager()->createFakeDriver());
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function smsManager(): SmsManager
    {
        return $this->app->make(SmsManager::class);
    }
}
