<?php

declare(strict_types=1);

use AliYavari\IranSms\Enums\Type;
use AliYavari\IranSms\Models\SmsLog;
use AliYavari\IranSms\Tests\Fixtures\TestDriver;
use Illuminate\Http\Client\ConnectionException;

/**
 * `HasLog` is tested through the concrete `TestDriver`, which uses it via the `Driver` class.
 * Its helper methods used here:
 *
 * - asSuccessful()         Forces the provider response to be successful (default).
 * - asFailed()             Forces the provider response to fail with code `12` and message `خطایی رخ داد.`.
 * - throwing()             Makes the next send call throw the given exception.
 * - prepare()              Prepares a sample SMS of the given type through `otp()`, `pattern()` or `text()`.
 *
 * Its default sender number is `5000555`.
 *
 * @see TestDriver
 */
dataset('type_log_methods', [
    'OTP' => ['method' => 'logOtp', 'type' => Type::Otp],
    'Pattern' => ['method' => 'logPattern', 'type' => Type::Pattern],
    'Text' => ['method' => 'logText', 'type' => Type::Text],
]);

it('does not log the SMS when logging is not enabled', function (Type $type): void {
    testDriver()->prepare($type)->send();

    $this->assertDatabaseEmpty(SmsLog::class);
})->with('sms_types');

it('does not log the SMS when logging is disabled', function (Type $type): void {
    testDriver()->log(false)->prepare($type)->send();

    $this->assertDatabaseEmpty(SmsLog::class);
})->with('sms_types');

it('logs the SMS of any type with its content when logging is enabled', function (Type $type, array $content): void {
    testDriver()->log()->prepare($type)->send();

    expect(SmsLog::query()->sole())
        ->type->toBe($type)
        ->content->toEqual($content);
})->with([
    'OTP' => ['type' => Type::Otp, 'content' => ['message' => 'OTP message']], // prepare() message
    'Pattern' => ['type' => Type::Pattern, 'content' => ['code' => 'pattern_code', 'variables' => ['name' => 'Ali']]], // prepare() pattern code and variables
    'Text' => ['type' => Type::Text, 'content' => ['message' => 'Text message']], // prepare() message
]);

it('stores the SMS details in the log', function (): void {
    testDriver()->log()->prepare(Type::Otp)->send(); // The type does not change this behaviour

    expect(SmsLog::query()->sole())
        ->driver->toBe('test') // Snake case of `TestDriver` class name
        ->from->toBe('5000555') // TestDriver's default sender
        ->to->toBe(['09123456789']) // prepare() phone
        ->is_successful->toBeTrue()
        ->error->toBeNull();
});

it('stores all the recipients of the SMS', function (): void {
    testDriver()->log()->text(['09123456789', '09129876543'], 'Text message')->send(); // The type does not change this behaviour

    expect(SmsLog::query()->sole())
        ->to->toBe(['09123456789', '09129876543']);
});

it('stores the sender set at runtime', function (): void {
    testDriver()->log()->prepare(Type::Otp)->from('1000111')->send(); // The type does not change this behaviour

    expect(SmsLog::query()->sole())
        ->from->toBe('1000111');
});

it('stores the failed status and the error of the failed SMS', function (): void {
    testDriver()->asFailed()->log()->prepare(Type::Otp)->send(); // The type does not change this behaviour

    expect(SmsLog::query()->sole())
        ->is_successful->toBeFalse()
        ->error->toBe('Code 12 - خطایی رخ داد.'); // asFailed() sets this error code and message
});

it('stores the driver name based on the naming convention', function (): void {
    $driver = Mockery::namedMock('\Class\Namespace\CustomNameDriver', TestDriver::class)->shouldAllowMockingProtectedMethods()->makePartial();

    $driver->log()->prepare(Type::Otp)->send(); // The type does not change this behaviour

    expect(SmsLog::query()->sole())
        ->driver->toBe('custom_name'); // Snake case of the driver class name without `Driver`
});

it('logs only the type enabled by its own log method', function (string $method, Type $type): void {
    foreach (Type::cases() as $smsType) {
        testDriver()->{$method}()->prepare($smsType)->send();
    }

    expect(SmsLog::query()->sole())
        ->type->toBe($type);
})->with('type_log_methods');

it('logs all the types enabled by their own log methods', function (): void {
    foreach (Type::cases() as $smsType) {
        testDriver()->logOtp()->logText()->prepare($smsType)->send(); // All type log methods share the same logic
    }

    $this->assertDatabaseCount(SmsLog::class, 2); // OTP and Text
    $this->assertDatabaseMissing(SmsLog::class, ['type' => Type::Pattern]);
});

it('does not log the type disabled by its own log method after enabling all types', function (string $method, Type $type): void {
    foreach (Type::cases() as $smsType) {
        testDriver()->log()->{$method}(false)->prepare($smsType)->send();
    }

    $this->assertDatabaseCount(SmsLog::class, 2); // The other two types
    $this->assertDatabaseMissing(SmsLog::class, ['type' => $type]);
})->with('type_log_methods');

it('logs only the successful SMS of any type when only successful logging is enabled', function (Type $type): void {
    testDriver()->asSuccessful()->logSuccessful()->prepare($type)->send();
    testDriver()->asFailed()->logSuccessful()->prepare($type)->send();

    expect(SmsLog::query()->sole())
        ->type->toBe($type)
        ->is_successful->toBeTrue();
})->with('sms_types');

it('logs only the failed SMS of any type when only failed logging is enabled', function (Type $type): void {
    testDriver()->asSuccessful()->logFailed()->prepare($type)->send();
    testDriver()->asFailed()->logFailed()->prepare($type)->send();

    expect(SmsLog::query()->sole())
        ->type->toBe($type)
        ->is_successful->toBeFalse();
})->with('sms_types');

it('does not log the disabled type when only successful logging is enabled', function (string $method, Type $type): void {
    foreach (Type::cases() as $smsType) {
        testDriver()->asSuccessful()->log()->{$method}(false)->logSuccessful()->prepare($smsType)->send();
    }

    $this->assertDatabaseCount(SmsLog::class, 2); // The other two types
    $this->assertDatabaseMissing(SmsLog::class, ['type' => $type]);
})->with('type_log_methods');

it('does not log the disabled type when only failed logging is enabled', function (string $method, Type $type): void {
    foreach (Type::cases() as $smsType) {
        testDriver()->asFailed()->log()->{$method}(false)->logFailed()->prepare($smsType)->send();
    }

    $this->assertDatabaseCount(SmsLog::class, 2); // The other two types
    $this->assertDatabaseMissing(SmsLog::class, ['type' => $type]);
})->with('type_log_methods');

it('does not log the SMS when the provider throws an exception', function (): void {
    $driver = testDriver()->throwing(new ConnectionException('Connection failed.'))->log()->prepare(Type::Otp); // The type does not change this behaviour

    expect(fn (): TestDriver => $driver->send())
        ->toThrow(ConnectionException::class, 'Connection failed.');

    $this->assertDatabaseEmpty(SmsLog::class);
});
