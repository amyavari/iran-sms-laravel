<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Concerns;

use AliYavari\IranSms\Concerns\HasLog;
use AliYavari\IranSms\Enums\Type;
use AliYavari\IranSms\Models\SmsLog;
use AliYavari\IranSms\Tests\Fixtures\HasLogTestDriver;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class HasLogTest extends TestCase
{
    #[Test]
    public function it_serialize_string_message_successfully(): void
    {
        // Otp
        $sms = $this->sms(Type::Otp, '091234567', 'OTP Message');

        $content = $this->callProtectedMethod($sms, 'serializeContent');

        $this->assertSame(['message' => 'OTP Message'], $content);

        // text
        $sms = $this->sms(Type::Text, '091234567', 'Text Message');

        $content = $this->callProtectedMethod($sms, 'serializeContent');

        $this->assertSame(['message' => 'Text Message'], $content);
    }

    #[Test]
    public function it_serialize_pattern_message_successfully(): void
    {
        $sms = $this->sms(Type::Pattern, '091234567', ['key' => 'value'], 'pattern_code');

        $content = $this->callProtectedMethod($sms, 'serializeContent');

        $this->assertSame([
            'code' => 'pattern_code',
            'variables' => ['key' => 'value'],
        ], $content);
    }

    #[Test]
    public function it_returns_driver_name(): void
    {
        /**
         * ------------------
         * Logic Explanation:
         * ------------------
         * Uses name convention of driver's class: *\CustomNameDriver => `custom_name`
         */
        $sms = Mockery::namedMock('\Class\Namespace\CustomNameDriver', HasLog::class);

        $driverName = $this->callProtectedMethod($sms, 'getDriverName');

        $this->assertSame('custom_name', $driverName);
    }

    #[Test]
    public function it_logs_otp_message_successfully(): void
    {
        $sms = $this->sms(Type::Otp, '091234567', 'OTP Message');

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
        $sms = $this->sms(Type::Text, '091234567', 'Text Message');

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
        $sms = $this->sms(Type::Pattern, '091234567', ['key' => 'value'], 'pattern_code');

        $this->callProtectedMethod($sms, 'storeLog');

        $this->assertDatabaseHas(SmsLog::class, [
            'type' => Type::Pattern,
            'to' => json_encode(['091234567']),
            'content' => json_encode($this->callProtectedMethod($sms, 'serializeContent')),  // This is not this test's concern.
        ]);
    }

    #[Test]
    public function it_logs_all_types_with_successful_status_successfully(): void
    {
        $this->getAllSmsTypes(from: '1234', successful: true, error: null)->each(function (HasLogTestDriver $sms) {
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
    public function it_logs_all_types_with_failed_status_successfully(): void
    {
        $this->getAllSmsTypes(from: '1234', successful: false, error: 'Test error message')->each(function (HasLogTestDriver $sms) {
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
    public function it_does_not_log_anything_if_user_did_not_set_log_config(): void
    {
        $this->getAllSmsTypes()->each(function (HasLogTestDriver $sms) {
            $sms->callHandleLog();
        });

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_does_not_log_anything_if_user_sets_log_to_false_for_all_types(): void
    {
        $this->getAllSmsTypes()->each(function (HasLogTestDriver $sms) {
            $sms->log(false)->callHandleLog();
        });

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_logs_everything_if_user_sets_log_all(): void
    {
        $this->getAllSmsTypes()->each(function (HasLogTestDriver $sms) {
            $sms->log(true)->callHandleLog();
        });

        $this->assertDatabaseCount(SmsLog::class, 3);
    }

    #[Test]
    public function it_logs_only_otp_messages_if_user_sets_log_to_only_otp(): void
    {
        $this->getAllSmsTypes()->each(function (HasLogTestDriver $sms) {
            $sms->logOtp(true)->callHandleLog();
        });

        $this->getAllSmsTypes()->each(function (HasLogTestDriver $sms) {
            $sms->log(false)->logOtp(true)->callHandleLog();
        });

        $otpLogs = SmsLog::query()->where('type', Type::Otp)->count();

        $this->assertSame(2, $otpLogs);
        $this->assertDatabaseCount(SmsLog::class, 2);
    }

    #[Test]
    public function it_does_not_log_otp_message_if_user_sets_it_not_to_log_otp(): void
    {
        $sms = $this->sms(Type::Otp, '091234567', 'OTP Message');
        $sms->log(true)->logOtp(false)->callHandleLog();

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_logs_only_text_messages_if_user_sets_log_to_only_text(): void
    {
        $this->getAllSmsTypes()->each(function (HasLogTestDriver $sms) {
            $sms->logText(true)->callHandleLog();
        });

        $this->getAllSmsTypes()->each(function (HasLogTestDriver $sms) {
            $sms->log(false)->logText(true)->callHandleLog();
        });

        $textLogs = SmsLog::query()->where('type', Type::Text)->count();

        $this->assertSame(2, $textLogs);
        $this->assertDatabaseCount(SmsLog::class, 2);
    }

    #[Test]
    public function it_does_not_log_text_message_if_user_sets_it_not_to_log_text(): void
    {
        $sms = $this->sms(Type::Text, '091234567', 'Text Message');
        $sms->log(true)->logText(false)->callHandleLog();

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_logs_only_pattern_messages_if_user_sets_log_to_only_pattern(): void
    {
        $this->getAllSmsTypes()->each(function (HasLogTestDriver $sms) {
            $sms->logPattern(true)->callHandleLog();
        });

        $this->getAllSmsTypes()->each(function (HasLogTestDriver $sms) {
            $sms->log(false)->logPattern(true)->callHandleLog();
        });

        $patternLogs = SmsLog::query()->where('type', Type::Pattern)->count();

        $this->assertSame(2, $patternLogs);
        $this->assertDatabaseCount(SmsLog::class, 2);
    }

    #[Test]
    public function it_does_not_log_pattern_message_if_user_sets_it_not_to_log_pattern(): void
    {
        $sms = $this->sms(Type::Pattern, '091234567', ['key' => 'value'], 'pattern_code');
        $sms->log(true)->logPattern(false)->callHandleLog();

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_only_logs_successful_messages_if_user_sets_it_to_log_successful(): void
    {
        // Must be logged
        $this->getAllSmsTypes(successful: true)->each(function (HasLogTestDriver $sms) {
            $sms->log(true)->logSuccessful()->callHandleLog();
        });

        // Won't log
        $this->getAllSmsTypes(successful: false)->each(function (HasLogTestDriver $sms) {
            $sms->log(true)->logSuccessful()->callHandleLog();
        });

        $successfulLogs = SmsLog::query()->where('is_successful', true)->count();

        $this->assertSame(3, $successfulLogs);
        $this->assertDatabaseCount(SmsLog::class, 3);
    }

    #[Test]
    public function it_calls_log_if_user_did_not_call_type_logs_before_only_successful_log(): void
    {
        $this->getAllSmsTypes(successful: true)->each(function (HasLogTestDriver $sms) {
            $sms->logSuccessful()->callHandleLog();
        });

        $this->assertDatabaseCount(SmsLog::class, 3);
    }

    #[Test]
    public function it_will_not_call_log_if_user_called_type_logs_before_only_successful_log(): void
    {
        $this->getAllSmsTypes(successful: true)->each(function (HasLogTestDriver $sms) {
            $sms->log(false)->logSuccessful()->callHandleLog();
        });

        $this->getAllSmsTypes(successful: true)->each(function (HasLogTestDriver $sms) {
            $sms->logOtp(false)->logSuccessful()->callHandleLog();
        });

        $this->getAllSmsTypes(successful: true)->each(function (HasLogTestDriver $sms) {
            $sms->logPattern(false)->logSuccessful()->callHandleLog();
        });

        $this->getAllSmsTypes(successful: true)->each(function (HasLogTestDriver $sms) {
            $sms->logText(false)->logSuccessful()->callHandleLog();
        });

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    #[Test]
    public function it_only_logs_failed_messages_if_user_sets_it_to_log_failed(): void
    {
        // Must be logged
        $this->getAllSmsTypes(successful: false)->each(function (HasLogTestDriver $sms) {
            $sms->log(true)->logFailed()->callHandleLog();
        });

        // Won't log
        $this->getAllSmsTypes(successful: true)->each(function (HasLogTestDriver $sms) {
            $sms->log(true)->logFailed()->callHandleLog();
        });

        $failedLogs = SmsLog::query()->where('is_successful', false)->count();

        $this->assertSame(3, $failedLogs);
        $this->assertDatabaseCount(SmsLog::class, 3);
    }

    #[Test]
    public function it_calls_log_if_user_did_not_call_type_logs_before_only_failed_log(): void
    {
        $this->getAllSmsTypes(successful: false)->each(function (HasLogTestDriver $sms) {
            $sms->logFailed()->callHandleLog();
        });

        $this->assertDatabaseCount(SmsLog::class, 3);
    }

    #[Test]
    public function it_will_not_call_log_if_user_called_type_logs_before_only_failed_log(): void
    {
        $this->getAllSmsTypes(successful: false)->each(function (HasLogTestDriver $sms) {
            $sms->log(false)->logSuccessful()->callHandleLog();
        });

        $this->getAllSmsTypes(successful: false)->each(function (HasLogTestDriver $sms) {
            $sms->logOtp(false)->logSuccessful()->callHandleLog();
        });

        $this->getAllSmsTypes(successful: false)->each(function (HasLogTestDriver $sms) {
            $sms->logPattern(false)->logSuccessful()->callHandleLog();
        });

        $this->getAllSmsTypes(successful: false)->each(function (HasLogTestDriver $sms) {
            $sms->logText(false)->logSuccessful()->callHandleLog();
        });

        $this->assertDatabaseEmpty(SmsLog::class);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function sms($type, $phones, $content, $patternCode = null, $successful = true, $error = null, $callHandleLoger = '123'): HasLogTestDriver
    {
        return new HasLogTestDriver($type, $phones, $content, $patternCode, $successful, $error, $callHandleLoger);
    }

    private function getAllSmsTypes($from = '123', $successful = true, $error = null): Collection
    {
        return collect([
            $this->sms(Type::Text, '091234567', 'Text Message', null, $successful, $error, $from),
            $this->sms(Type::Otp, '091234567', 'OTP Message', null, $successful, $error, $from),
            $this->sms(Type::Pattern, '091234567', ['key' => 'value'], 'pattern_code', $successful, $error, $from),
        ]);
    }
}
