<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Channels;

use AliYavari\IranSms\Channels\SmsChannel;
use AliYavari\IranSms\Contracts\Sms;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Notifications\Notification;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use UnexpectedValueException;

final class SmsChannelTest extends TestCase
{
    #[Test]
    public function it_sends_sms(): void
    {
        $sms = Mockery::spy(Sms::class);

        $notification = Mockery::mock(Notification::class);
        $notification->shouldReceive('toSms')->once()->andReturn($sms);

        $this->channel()->send(new stdClass, $notification);

        $sms->shouldHaveReceived('send')->once();
    }

    #[Test]
    public function it_throws_an_exception_if_notification_does_not_return_sms_instance(): void
    {
        $notification = Mockery::mock(Notification::class);
        $notification->shouldReceive('toSms')->once()->andReturn('sms');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The toSms() method must return an instance of "\AliYavari\IranSms\Contracts\Sms", "string" given.');

        $this->channel()->send(new stdClass, $notification);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function channel(): SmsChannel
    {
        return $this->app->make(SmsChannel::class);
    }
}
