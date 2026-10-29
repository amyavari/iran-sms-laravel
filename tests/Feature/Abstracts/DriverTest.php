<?php

declare(strict_types=1);

use AliYavari\IranSms\Enums\Type;
use AliYavari\IranSms\Exceptions\SmsContentNotDefinedException;
use AliYavari\IranSms\Exceptions\SmsIsImmutableException;
use AliYavari\IranSms\Exceptions\SmsNotSentYetException;
use AliYavari\IranSms\Tests\Fixtures\TestDriver;
use Illuminate\Http\Client\ConnectionException;

/**
 * To keep test cases simple and readable, the concrete `TestDriver` provides
 * several helper methods:
 *
 * - asSuccessful()         Forces the provider response to be successful (default).
 * - asFailed()             Forces the provider response to fail with code `12` and message `خطایی رخ داد.`.
 * - throwing()             Makes the next send call throw the given exception.
 * - prepare()              Prepares a sample SMS of the given type through `otp()`, `pattern()` or `text()`.
 * - calledMethod()         Returns the child class method that was called.
 * - receivedParameters()   Returns parameters received by the last invoked child method.
 *
 * Its default sender number is `5000555`.
 *
 * @see TestDriver
 */
it('sends an OTP SMS with the given phone, message and the default sender', function (): void {
    $driver = testDriver()->otp('09123456789', 'OTP message')->send();

    expect($driver)
        ->calledMethod()->toBe('sendOtp')
        ->receivedParameters('phone')->toBe('09123456789')
        ->receivedParameters('message')->toBe('OTP message')
        ->receivedParameters('from')->toBe('5000555'); // TestDriver's default sender
});

it('sends a pattern SMS with the given phones, pattern code, variables and the default sender', function (): void {
    $driver = testDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['name' => 'Ali', 'code' => '1234'])->send();

    expect($driver)
        ->calledMethod()->toBe('sendPattern')
        ->receivedParameters('phones')->toBe(['09123456789', '09129876543'])
        ->receivedParameters('patternCode')->toBe('pattern_code')
        ->receivedParameters('variables')->toEqual(['name' => 'Ali', 'code' => '1234'])
        ->receivedParameters('from')->toBe('5000555'); // TestDriver's default sender
});

it('sends a text SMS with the given phones, message and the default sender', function (): void {
    $driver = testDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    expect($driver)
        ->calledMethod()->toBe('sendText')
        ->receivedParameters('phones')->toBe(['09123456789', '09129876543'])
        ->receivedParameters('message')->toBe('Text message')
        ->receivedParameters('from')->toBe('5000555'); // TestDriver's default sender
});

it('wraps a single phone in a list for pattern and text SMS', function (Type $type): void {
    $driver = testDriver()->prepare($type)->send();

    expect($driver)
        ->receivedParameters('phones')->toBe(['09123456789']); // prepare() phone
})->with([
    'Pattern' => ['type' => Type::Pattern],
    'Text' => ['type' => Type::Text],
]);

it('sends the SMS with the sender set at runtime', function (Type $type): void {
    $driver = testDriver()->prepare($type)->from('1000111')->send();

    expect($driver)
        ->receivedParameters('from')->toBe('1000111');
})->with('sms_types');

it('throws an exception when the SMS content is defined twice', function (Type $firstType, Type $type): void {
    $driver = testDriver()->prepare($firstType);

    expect(fn (): TestDriver => $driver->prepare($type))
        ->toThrow(SmsIsImmutableException::class, 'SMS object is immutable, to create new SMS content you need to create new instance.');
})->with([
    'first OTP' => ['firstType' => Type::Otp],
    'first Pattern' => ['firstType' => Type::Pattern],
    'first Text' => ['firstType' => Type::Text],
])->with('sms_types');

it('throws an exception when sending without defining the SMS content', function (): void {
    $driver = testDriver();

    expect(fn (): TestDriver => $driver->send())
        ->toThrow(SmsContentNotDefinedException::class, 'Before sending an SMS you must define its content by one of these methods "otp, pattern, text".');

    expect($driver)
        ->calledMethod()->toBeNull(); // Nothing is sent
});

it('throws an exception when the status is checked before sending', function (string $method, Type $type): void {
    $driver = testDriver()->prepare($type);

    expect(fn (): mixed => $driver->{$method}())
        ->toThrow(SmsNotSentYetException::class, 'To check SMS status, you first must send it with "send".');
})->with([
    'successful',
    'failed',
    'error',
])->with('sms_types');

it('returns a successful status with no error when the provider succeeds', function (Type $type): void {
    $driver = testDriver()->asSuccessful()->prepare($type)->send();

    expect($driver)
        ->successful()->toBeTrue()
        ->error()->toBeNull();
})->with('sms_types');

it('returns a failed status with a formatted error when the provider fails', function (Type $type): void {
    $driver = testDriver()->asFailed()->prepare($type)->send();

    expect($driver)
        ->successful()->toBeFalse()
        ->error()->toBe('Code 12 - خطایی رخ داد.'); // asFailed() sets this error code and message
})->with('sms_types');

it('returns failed as the inverse of successful', function (): void {
    // Successful
    $driver = testDriver()->asSuccessful()->prepare(Type::Otp)->send();

    expect($driver)
        ->failed()->toBeFalse();

    // Failed
    $driver = testDriver()->asFailed()->prepare(Type::Otp)->send();

    expect($driver)
        ->failed()->toBeTrue();
});

it('keeps the SMS unsent when the provider throws an exception', function (Type $type): void {
    $driver = testDriver()->throwing(new ConnectionException('Connection failed.'))->prepare($type);

    expect(fn (): TestDriver => $driver->send())
        ->toThrow(ConnectionException::class, 'Connection failed.');

    expect(fn (): bool => $driver->successful())
        ->toThrow(SmsNotSentYetException::class, 'To check SMS status, you first must send it with "send".');
})->with('sms_types');
