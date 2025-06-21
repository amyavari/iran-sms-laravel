<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit;

use AliYavari\IranSms\SmsManager;
use AliYavari\IranSms\Tests\Fixtures\TestDriver;
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
        $testDriver = new TestDriver('123456', true);

        $this->smsManager()->setDriver('test_driver', $testDriver);

        $retrievedDriver = $this->smsManager()->driver('test_driver');

        $this->assertInstanceOf(TestDriver::class, $retrievedDriver);
        $this->assertSame('123456', $this->callProtectedMethod($retrievedDriver, 'getDefaultSender'));
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function smsManager(): SmsManager
    {
        return $this->app->make(SmsManager::class);
    }
}
