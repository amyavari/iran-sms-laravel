<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Abstracts;

use AliYavari\IranSms\Enums\Type;
use AliYavari\IranSms\Exceptions\SmsContentNotDefinedException;
use AliYavari\IranSms\Exceptions\SmsIsImmutableException;
use AliYavari\IranSms\Exceptions\SmsNotSentYetException;
use AliYavari\IranSms\Models\SmsLog;
use AliYavari\IranSms\Tests\Fixtures\TestDriver;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class DriverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTables();
    }

    #[Test]
    public function it_returns_default_sender_number_if_user_did_not_set_it(): void
    {
        $sms = $this->sms(from: '1234');

        $senderNumber = $this->callProtectedMethod($sms, 'getSender');

        $this->assertSame('1234', $senderNumber);
        $this->assertSame('getDefaultSender', $sms->whatIsCalled);
    }

    #[Test]
    public function it_returns_user_sender_number_if_user_set_it(): void
    {
        $sms = $this->sms(from: '1234');
        $returnedSms = $sms->from('123456789');

        $senderNumber = $this->callProtectedMethod($sms, 'getSender');

        $this->assertInstanceOf(TestDriver::class, $returnedSms);
        $this->assertSame('123456789', $senderNumber);
    }

    #[Test]
    public function it_creates_and_sends_otp_sms_successfully(): void
    {
        $sms = $this->sms(from: '1234');

        $sms->otp('091234567', 'OTP Message')->send();

        $this->assertInstanceOf(TestDriver::class, $sms);
        $this->assertSame('sendOtp', $sms->whatIsCalled);
        $this->assertSame([
            'phone' => '091234567',
            'message' => 'OTP Message',
            'from' => '1234',
        ], $sms->receivedArguments);
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

        $this->assertInstanceOf(TestDriver::class, $sms);
        $this->assertSame('sendPattern', $sms->whatIsCalled);
        $this->assertSame([
            'phones' => ['091234567'],
            'code' => 'pattern_code',
            'variables' => ['key' => 'value'],
            'from' => '1234',
        ], $sms->receivedArguments);
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

        $this->assertInstanceOf(TestDriver::class, $sms);
        $this->assertSame('sendText', $sms->whatIsCalled);
        $this->assertSame([
            'phones' => ['091234567'],
            'message' => 'Text Message',
            'from' => '1234',
        ], $sms->receivedArguments);
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
        $this->getAllSmsTypes(from: '1234')->each(function (TestDriver $sms) {
            $sms->from('4567')->send();

            $this->assertSame('4567', $sms->receivedArguments['from']);
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
        $this->assertSame('isSuccessful', $sms->whatIsCalled);

        // Failed
        $sms = $this->sms(successful: false);
        $sms->otp('091234567', 'OTP Message')->send();

        $this->assertFalse($sms->successful());
        $this->assertSame('isSuccessful', $sms->whatIsCalled);
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
        $this->assertSame('isSuccessful', $sms->whatIsCalled);

        // Failed
        $sms = $this->sms(successful: false);
        $sms->otp('091234567', 'OTP Message')->send();

        $this->assertTrue($sms->failed());
        $this->assertSame('isSuccessful', $sms->whatIsCalled);
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
        /**
         * ------------------
         * Logic Explanation:
         * ------------------
         * We are not sure if `getErrorMessage` on the driver class, can handle successful status,
         * So we need to make sure this method won't be called in the successful status.
         */
        $this->assertNotSame('getErrorMessage', $sms->whatIsCalled);
    }

    #[Test]
    public function ir_returns_error_message_if_sending_was_not_successful(): void
    {
        $sms = $this->sms(successful: false);
        $sms->otp('091234567', 'OTP Message')->send();

        $this->assertSame('Test error message', $sms->error());
        $this->assertSame('getErrorMessage', $sms->whatIsCalled);
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
    public function it_serialize_string_message_successfully(): void
    {
        // Otp
        $sms = $this->sms();
        $sms->otp('091234567', 'OTP Message');

        $content = $this->callProtectedMethod($sms, 'serializeContent');

        $this->assertSame(['message' => 'OTP Message'], $content);

        // text
        $sms = $this->sms();
        $sms->text('091234567', 'Text Message');

        $content = $this->callProtectedMethod($sms, 'serializeContent');

        $this->assertSame(['message' => 'Text Message'], $content);
    }

    #[Test]
    public function it_serialize_pattern_message_successfully(): void
    {
        $sms = $this->sms();

        $sms->pattern('091234567', 'pattern_code', ['key' => 'value']);

        $content = $this->callProtectedMethod($sms, 'serializeContent');

        $this->assertSame([
            'code' => 'pattern_code',
            'variables' => ['key' => 'value'],
        ], $content);
    }

    #[Test]
    public function it_returns_driver_name(): void
    {
        $sms = Mockery::namedMock('\Class\Namespace\CustomNameDriver', TestDriver::class);

        $driverName = $this->callProtectedMethod($sms, 'getDriverName');
        /**
         * ------------------
         * Logic Explanation:
         * ------------------
         * Uses name convention of driver's class: *\CustomNameDriver => `custom name`
         */
        $this->assertSame('custom_name', $driverName);
    }

    #[Test]
    public function it_logs_otp_message_successfully(): void
    {
        $sms = $this->sms();
        $sms->otp('091234567', 'OTP Message')->send();

        $this->callProtectedMethod($sms, 'storeLog');

        $this->assertDatabaseHas(SmsLog::class, [
            'type' => Type::Otp,
            'to' => json_encode(['091234567']),
            'content' => json_encode($this->callProtectedMethod($sms, 'serializeContent')),  // This is not this test's concern.
        ]);
    }

    #[Test]
    public function it_logs_text_message_successfully(): void
    {
        $sms = $this->sms();
        $sms->text('091234567', 'Text Message')->send();

        $this->callProtectedMethod($sms, 'storeLog');

        $this->assertDatabaseHas(SmsLog::class, [
            'type' => Type::Text,
            'to' => json_encode(['091234567']),
            'content' => json_encode($this->callProtectedMethod($sms, 'serializeContent')),  // This is not this test's concern.
        ]);
    }

    #[Test]
    public function it_logs_pattern_message_successfully(): void
    {
        $sms = $this->sms();
        $sms->pattern('091234567', 'pattern_code', ['key' => 'value'])->send();

        $this->callProtectedMethod($sms, 'storeLog');

        $this->assertDatabaseHas(SmsLog::class, [
            'type' => Type::Pattern,
            'to' => json_encode(['091234567']),
            'content' => json_encode($this->callProtectedMethod($sms, 'serializeContent')),  // This is not this test's concern.
        ]);
    }

    #[Test]
    public function it_logs_all_types_with_correct_data_if_sending_was_successful(): void
    {
        $this->getAllSmsTypes(from: '1234', successful: true)->each(function (TestDriver $sms) {
            $sms->send();

            $this->callProtectedMethod($sms, 'storeLog');
        });

        $logsCount = SmsLog::query()
            ->where('from', '1234')
            ->where('is_successful', true)
            ->whereNull('error')
            ->count();

        $this->assertSame(3, $logsCount);
    }

    #[Test]
    public function it_logs_all_types_with_correct_data_if_sending_failed(): void
    {
        $this->getAllSmsTypes(from: '1234', successful: false)->each(function (TestDriver $sms) {
            $sms->send();

            $this->callProtectedMethod($sms, 'storeLog');
        });

        $logsCount = SmsLog::query()
            ->where('from', '1234')
            ->where('is_successful', false)
            ->where('error', 'Test error message')
            ->count();

        $this->assertSame(3, $logsCount);
    }

    #[Test]
    public function it_logs_all_types_with_correct_data_with_user_defined_sender(): void
    {
        $this->getAllSmsTypes(from: '1234')->each(function (TestDriver $sms) {
            $sms->from('4567')->send();

            $this->callProtectedMethod($sms, 'storeLog');
        });

        $logsCount = SmsLog::query()
            ->where('from', '4567')
            ->count();

        $this->assertSame(3, $logsCount);
    }

    #[Test]
    public function it_does_not_log_anything_if_user_did_not_set_log_config(): void
    {
        $this->getAllSmsTypes()->each(function (TestDriver $sms) {
            $sms->send();
        });

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_does_not_log_anything_if_user_sets_log_to_false_for_all_types(): void
    {
        $this->getAllSmsTypes()->each(function (TestDriver $sms) {
            $sms->log(false)->send();
        });

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_logs_everything_if_user_sets_log_all(): void
    {
        $this->getAllSmsTypes()->each(function (TestDriver $sms) {
            $sms->log(true)->send();
        });

        $this->assertDatabaseCount(SmsLog::class, 3);
    }

    #[Test]
    public function it_logs_only_otp_messages_if_user_sets_log_to_only_otp(): void
    {
        $this->getAllSmsTypes()->each(function (TestDriver $sms) {
            $sms->logOtp(true)->send();
        });

        $this->getAllSmsTypes()->each(function (TestDriver $sms) {
            $sms->log(false)->logOtp(true)->send();
        });

        $otpLogs = SmsLog::query()->where('type', Type::Otp)->count();

        $this->assertSame(2, $otpLogs);
        $this->assertDatabaseCount(SmsLog::class, 2);
    }

    #[Test]
    public function it_does_not_log_otp_message_if_user_sets_it_not_to_log_otp(): void
    {
        $sms = $this->sms();
        $sms->log(true)->logOtp(false)->otp('091234567', 'OTP Message')->send();

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_logs_only_text_messages_if_user_sets_log_to_only_text(): void
    {
        $this->getAllSmsTypes()->each(function (TestDriver $sms) {
            $sms->logText(true)->send();
        });

        $this->getAllSmsTypes()->each(function (TestDriver $sms) {
            $sms->log(false)->logText(true)->send();
        });

        $textLogs = SmsLog::query()->where('type', Type::Text)->count();

        $this->assertSame(2, $textLogs);
        $this->assertDatabaseCount(SmsLog::class, 2);
    }

    #[Test]
    public function it_does_not_log_text_message_if_user_sets_it_not_to_log_text(): void
    {
        $sms = $this->sms();
        $sms->log(true)->logText(false)->text('091234567', 'Text Message')->send();

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_logs_only_pattern_messages_if_user_sets_log_to_only_pattern(): void
    {
        $this->getAllSmsTypes()->each(function (TestDriver $sms) {
            $sms->logPattern(true)->send();
        });

        $this->getAllSmsTypes()->each(function (TestDriver $sms) {
            $sms->log(false)->logPattern(true)->send();
        });

        $patternLogs = SmsLog::query()->where('type', Type::Pattern)->count();

        $this->assertSame(2, $patternLogs);
        $this->assertDatabaseCount(SmsLog::class, 2);
    }

    #[Test]
    public function it_does_not_log_pattern_message_if_user_sets_it_not_to_log_pattern(): void
    {
        $sms = $this->sms();
        $sms->log(true)->logPattern(false)->pattern('091234567', 'pattern_code', ['key' => 'value'])->send();

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_only_logs_successful_messages_if_user_sets_it_to_log_successful(): void
    {
        // Must be logged
        $this->getAllSmsTypes(successful: true)->each(function (TestDriver $sms) {
            $sms->log(true)->logSuccessful()->send();
        });

        // Won't log
        $this->getAllSmsTypes(successful: false)->each(function (TestDriver $sms) {
            $sms->log(true)->logSuccessful()->send();
        });

        $successfulLogs = SmsLog::query()->where('is_successful', true)->count();

        $this->assertSame(3, $successfulLogs);
        $this->assertDatabaseCount(SmsLog::class, 3);
    }

    #[Test]
    public function it_calls_log_if_user_did_not_call_type_logs_before_only_successful_log(): void
    {
        $this->getAllSmsTypes(successful: true)->each(function (TestDriver $sms) {
            $sms->logSuccessful()->send();
        });

        $this->assertDatabaseCount(SmsLog::class, 3);
    }

    #[Test]
    public function it_will_not_call_log_if_user_called_type_logs_before_only_successful_log(): void
    {
        $this->getAllSmsTypes(successful: true)->each(function (TestDriver $sms) {
            $sms->log(false)->logSuccessful()->send();
        });

        $this->getAllSmsTypes(successful: true)->each(function (TestDriver $sms) {
            $sms->logOtp(false)->logSuccessful()->send();
        });

        $this->getAllSmsTypes(successful: true)->each(function (TestDriver $sms) {
            $sms->logPattern(false)->logSuccessful()->send();
        });

        $this->getAllSmsTypes(successful: true)->each(function (TestDriver $sms) {
            $sms->logText(false)->logSuccessful()->send();
        });

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_only_logs_failed_messages_if_user_sets_it_to_log_failed(): void
    {
        // Must be logged
        $this->getAllSmsTypes(successful: false)->each(function (TestDriver $sms) {
            $sms->log(true)->logFailed()->send();
        });

        // Won't log
        $this->getAllSmsTypes(successful: true)->each(function (TestDriver $sms) {
            $sms->log(true)->logFailed()->send();
        });

        $failedLogs = SmsLog::query()->where('is_successful', false)->count();

        $this->assertSame(3, $failedLogs);
        $this->assertDatabaseCount(SmsLog::class, 3);
    }

    #[Test]
    public function it_calls_log_if_user_did_not_call_type_logs_before_only_failed_log(): void
    {
        $this->getAllSmsTypes(successful: false)->each(function (TestDriver $sms) {
            $sms->logFailed()->send();
        });

        $this->assertDatabaseCount(SmsLog::class, 3);
    }

    #[Test]
    public function it_will_not_call_log_if_user_called_type_logs_before_only_failed_log(): void
    {
        $this->getAllSmsTypes(successful: false)->each(function (TestDriver $sms) {
            $sms->log(false)->logSuccessful()->send();
        });

        $this->getAllSmsTypes(successful: false)->each(function (TestDriver $sms) {
            $sms->logOtp(false)->logSuccessful()->send();
        });

        $this->getAllSmsTypes(successful: false)->each(function (TestDriver $sms) {
            $sms->logPattern(false)->logSuccessful()->send();
        });

        $this->getAllSmsTypes(successful: false)->each(function (TestDriver $sms) {
            $sms->logText(false)->logSuccessful()->send();
        });

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function sms(string $from = '123', bool $successful = true): TestDriver
    {
        return new TestDriver($from, $successful);
    }

    private function migrateTables(): void
    {
        // TODO: get the schema from stub file.

        if (! Schema::hasTable('sms_logs')) {
            Schema::create('sms_logs', function (Blueprint $table) {
                $table->id();
                $table->string('type', 10);
                $table->string('driver', 20);
                $table->string('from', 20);
                $table->json('to');
                $table->json('content');
                $table->boolean('is_successful');
                $table->string('error')->nullable();
                $table->timestamps();
            });
        }
    }

    private function getAllSmsTypes(string $from = '123', bool $successful = true): Collection
    {
        return collect([
            $this->sms($from, $successful)->text('091234567', 'Text Message'),
            $this->sms($from, $successful)->otp('091234567', 'OTP Message'),
            $this->sms($from, $successful)->pattern('091234567', 'pattern_code', ['key' => 'value']),
        ]);
    }
}
