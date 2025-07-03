<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Abstracts;

use AliYavari\IranSms\Exceptions\SmsContentNotDefinedException;
use AliYavari\IranSms\Exceptions\SmsIsImmutableException;
use AliYavari\IranSms\Exceptions\SmsNotSentYetException;
use AliYavari\IranSms\Models\SmsLog;
use AliYavari\IranSms\Tests\Fixtures\ConcreteTestDriver;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;

final class DriverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_default_sender_number_if_user_did_not_set_it(): void
    {
        $sms = $this->sms(from: '1234');

        $senderNumber = $this->callProtectedMethod($sms, 'getSender');

        $this->assertSame('1234', $senderNumber);
    }

    #[Test]
    public function it_returns_user_sender_number_if_user_set_it(): void
    {
        $sms = $this->sms(from: '1234');
        $returnedSms = $sms->from('123456789');

        $senderNumber = $this->callProtectedMethod($sms, 'getSender');

        $this->assertInstanceOf(ConcreteTestDriver::class, $returnedSms);
        $this->assertSame('123456789', $senderNumber);
    }

    #[Test]
    public function it_creates_and_sends_otp_sms_successfully(): void
    {
        $sms = $this->sms(from: '1234');

        $sms->otp('091234567', 'OTP Message')->send();

        $this->assertInstanceOf(ConcreteTestDriver::class, $sms);
        $this->assertSame([
            'type' => 'otp',
            'phone' => '091234567',
            'message' => 'OTP Message',
            'from' => '1234',
        ], $sms->dataToAssert);
    }

    #[Test]
    public function it_throws_an_exception_if_user_wants_to_create_otp_sms_on_existing_sms_instance(): void
    {
        $sms = $this->sms();
        $sms->otp('091234567', 'OTP Message');

        $this->expectException(SmsIsImmutableException::class);
        $this->expectExceptionMessage('SMS object is immutable, to create new SMS content you need to create new instance.');

        $sms->otp('091234567', 'OTP Message');
    }

    #[Test]
    public function it_creates_and_sends_pattern_sms_successfully(): void
    {
        $sms = $this->sms(from: '1234');

        $sms->pattern('091234567', 'pattern_code', ['key' => 'value'])->send();

        $this->assertInstanceOf(ConcreteTestDriver::class, $sms);
        $this->assertSame([
            'type' => 'pattern',
            'phones' => ['091234567'],
            'code' => 'pattern_code',
            'variables' => ['key' => 'value'],
            'from' => '1234',
        ], $sms->dataToAssert);
    }

    #[Test]
    public function it_throws_an_exception_if_user_wants_to_create_pattern_sms_on_existing_sms_instance(): void
    {
        $sms = $this->sms();
        $sms->pattern('091234567', 'pattern_code', ['key' => 'value']);

        $this->expectException(SmsIsImmutableException::class);
        $this->expectExceptionMessage('SMS object is immutable, to create new SMS content you need to create new instance.');

        $sms->pattern('091234567', 'pattern_code', ['key' => 'value']);
    }

    #[Test]
    public function it_creates_and_sends_text_sms_successfully(): void
    {
        $sms = $this->sms(from: '1234');

        $sms->text('091234567', 'Text Message')->send();

        $this->assertInstanceOf(ConcreteTestDriver::class, $sms);
        $this->assertSame([
            'type' => 'text',
            'phones' => ['091234567'],
            'message' => 'Text Message',
            'from' => '1234',
        ], $sms->dataToAssert);
    }

    #[Test]
    public function it_throws_an_exception_if_user_wants_to_create_text_sms_on_existing_sms_instance(): void
    {
        $sms = $this->sms();
        $sms->text('091234567', 'Text Message');

        $this->expectException(SmsIsImmutableException::class);
        $this->expectExceptionMessage('SMS object is immutable, to create new SMS content you need to create new instance.');

        $sms->text('091234567', 'Text Message');
    }

    #[Test]
    public function it_creates_and_sends_sms_with_user_defined_sender_successfully(): void
    {
        $this->getAllSmsTypes(from: '1234')->each(function (ConcreteTestDriver $sms) {
            $sms->from('4567')->send();

            $this->assertSame('4567', $sms->dataToAssert['from']);
        });
    }

    #[Test]
    public function it_throws_an_exception_if_message_content_is_not_set(): void
    {
        $sms = $this->sms();

        $this->expectException(SmsContentNotDefinedException::class);
        $this->expectExceptionMessage('Before sending an SMS you must define its content by one of these methods "otp, pattern, text".');

        $sms->send();
    }

    #[Test]
    public function it_checks_sending_sms_is_successful(): void
    {
        // Successful
        $sms = $this->sms(successful: true);
        $sms->otp('091234567', 'OTP Message')->send();

        $this->assertTrue($sms->successful());

        // Failed
        $sms = $this->sms(successful: false);
        $sms->otp('091234567', 'OTP Message')->send();

        $this->assertFalse($sms->successful());
    }

    #[Test]
    public function it_throws_an_exception_if_check_successful_before_sending_message(): void
    {
        $sms = $this->sms();
        $sms->otp('091234567', 'OTP Message');

        $this->expectException(SmsNotSentYetException::class);
        $this->expectExceptionMessage('To check SMS status, you first must send it with "send".');

        $sms->successful();
    }

    #[Test]
    public function it_checks_sending_sms_is_failed(): void
    {
        // Successful
        $sms = $this->sms(successful: true);
        $sms->otp('091234567', 'OTP Message')->send();

        $this->assertFalse($sms->failed());

        // Failed
        $sms = $this->sms(successful: false);
        $sms->otp('091234567', 'OTP Message')->send();

        $this->assertTrue($sms->failed());
    }

    #[Test]
    public function it_throws_an_exception_if_check_failed_before_sending_message(): void
    {
        $sms = $this->sms();
        $sms->otp('091234567', 'OTP Message');

        $this->expectException(SmsNotSentYetException::class);
        $this->expectExceptionMessage('To check SMS status, you first must send it with "send".');

        $sms->failed();
    }

    #[Test]
    public function it_returns_null_as_error_if_sending_was_successful(): void
    {
        $sms = $this->sms(successful: true);
        $sms->otp('091234567', 'OTP Message')->send();

        $this->assertNull($sms->error());
    }

    #[Test]
    public function ir_returns_error_message_if_sending_was_not_successful(): void
    {
        $sms = $this->sms(successful: false);
        $sms->otp('091234567', 'OTP Message')->send();

        $this->assertSame('Code 40 - Test error message', $sms->error());
    }

    #[Test]
    public function it_throws_an_exception_if_check_error_before_sending_message(): void
    {
        $sms = $this->sms();
        $sms->text('0913', 'test');

        $this->expectException(SmsNotSentYetException::class);
        $this->expectExceptionMessage('To check SMS status, you first must send it with "send".');

        $sms->error();
    }

    #[Test]
    public function it_calls_handle_log_after_sending_sms(): void
    {
        $this->getAllSmsTypes(successful: true)->each(function (ConcreteTestDriver $sms) {
            $sms->logSuccessful()->send(); // Must be logged
        });

        $this->assertDatabaseCount(SmsLog::class, 3);

        $this->getAllSmsTypes(successful: true)->each(function (ConcreteTestDriver $sms) {
            $sms->logFailed()->send(); // Must not be logged
        });

        $this->assertDatabaseCount(SmsLog::class, 3);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function sms($from = '123', $successful = true): ConcreteTestDriver
    {
        return new ConcreteTestDriver($from, $successful);
    }

    private function getAllSmsTypes($from = '123', $successful = true): Collection
    {
        return collect([
            $this->sms($from, $successful)->text('091234567', 'Text Message'),
            $this->sms($from, $successful)->otp('091234567', 'OTP Message'),
            $this->sms($from, $successful)->pattern('091234567', 'pattern_code', ['key' => 'value']),
        ]);
    }
}
