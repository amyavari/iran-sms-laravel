<?php

declare(strict_types=1);

use AliYavari\IranSms\Contracts\Sms as SmsContract;
use AliYavari\IranSms\Drivers\FakeDriver;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('iran-sms.default', 'kavenegar'); // Any real provider, the tests only need a known default
});

it('fakes the default provider as successful when nothing is given', function (): void {
    Sms::fake();

    expect(Sms::otp('09123456789', 'OTP message')->send()) // The type does not change this behaviour
        ->toBeInstanceOf(FakeDriver::class)
        ->toBeSuccessfulSms();
});

it('fakes the default provider when it is listed as "default"', function (): void {
    Sms::fake(['default']);

    expect(Sms::otp('09123456789', 'OTP message')->send()) // The type does not change this behaviour
        ->toBeInstanceOf(FakeDriver::class)
        ->toBeSuccessfulSms();
});

it('fakes every listed provider as successful when no response is given', function (): void {
    Sms::fake(['sms_ir', 'meli_payamak']);

    foreach (['sms_ir', 'meli_payamak'] as $provider) {
        expect(Sms::provider($provider)->otp('09123456789', 'OTP message')->send()) // The type does not change this behaviour
            ->toBeInstanceOf(FakeDriver::class)
            ->toBeSuccessfulSms();
    }
});

it('fakes every listed provider with the given successful response', function (): void {
    Sms::fake(['sms_ir', 'meli_payamak'], Sms::successfulRequest());

    foreach (['sms_ir', 'meli_payamak'] as $provider) {
        expect(Sms::provider($provider)->otp('09123456789', 'OTP message')->send()) // The type does not change this behaviour
            ->toBeInstanceOf(FakeDriver::class)
            ->toBeSuccessfulSms();
    }
});

it('fakes every listed provider with the given failed response', function (): void {
    Sms::fake(['sms_ir', 'meli_payamak'], Sms::failedRequest('Custom error message', 40));

    foreach (['sms_ir', 'meli_payamak'] as $provider) {
        expect(Sms::provider($provider)->otp('09123456789', 'OTP message')->send()) // The type does not change this behaviour
            ->toBeInstanceOf(FakeDriver::class)
            ->toBeFailedSms('40', 'Custom error message');
    }
});

it('fakes every listed provider with the given failed connection for any type', function (): void {
    Sms::fake(['sms_ir', 'meli_payamak'], Sms::failedConnection());

    foreach (['sms_ir', 'meli_payamak'] as $provider) {
        // Only the class is asserted, Laravel builds the message
        expect(fn (): SmsContract => Sms::provider($provider)->otp('09123456789', 'OTP message')->send())
            ->toThrow(ConnectionException::class);

        expect(fn (): SmsContract => Sms::provider($provider)->pattern('09123456789', 'pattern_code', ['name' => 'Ali'])->send())
            ->toThrow(ConnectionException::class);

        expect(fn (): SmsContract => Sms::provider($provider)->text('09123456789', 'Text message')->send())
            ->toThrow(ConnectionException::class);
    }
});

it('fakes each provider with its own response', function (): void {
    Sms::fake([
        'default' => Sms::failedRequest(),
        'sms_ir' => Sms::successfulRequest(),
        'meli_payamak' => Sms::failedConnection(),
    ]);

    // The type does not change this behaviour
    expect(Sms::otp('09123456789', 'OTP message')->send())
        ->toBeFailedSms('0', 'Error Message'); // failedRequest() default error code and message

    expect(Sms::provider('sms_ir')->otp('09123456789', 'OTP message')->send())
        ->toBeSuccessfulSms();

    expect(fn (): mixed => Sms::provider('meli_payamak')->otp('09123456789', 'OTP message')->send())
        ->toThrow(ConnectionException::class); // Only the class is asserted, Laravel builds the message
});

it('throws an exception when both a global response and per-provider responses are given', function (): void {
    expect(fn () => Sms::fake(['sms_ir' => Sms::successfulRequest()], Sms::successfulRequest()))
        ->toThrow(InvalidArgumentException::class, 'Invalid fake setup: you cannot provide both a global mock response and per-provider responses at the same time.');
});

it('throws an exception when a per-provider response is not a mock response', function (): void {
    expect(fn () => Sms::fake(['sms_ir' => [Sms::successfulRequest()]]))
        ->toThrow(UnexpectedValueException::class, "Collection should only include [AliYavari\IranSms\Dtos\MockResponse] items, but 'array' found at position 0.");
});

it('throws an exception when a listed provider is not a string', function (): void {
    expect(fn () => Sms::fake([true, 'sms_ir']))
        ->toThrow(UnexpectedValueException::class, "Collection should only include [string] items, but 'bool' found at position 0.");
});
