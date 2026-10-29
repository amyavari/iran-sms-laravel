<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\SmsIrDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

dataset('sms_ir_messages', [
    'Text' => fn (): SmsIrDriver => smsIrDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): SmsIrDriver => smsIrDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345']),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.sms_ir.token', 'sms_token');
    Config::set('iran-sms.providers.sms_ir.from', '5000555');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['status' => 1]);

    smsIrDriver()->text('09123456789', 'Text message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.sms.ir/v1/send/bulk')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('Accept')->toBe(['application/json'])
        ->header('x-api-key')->toBe(['sms_token']); // Config token

    expect($request->data())
        ->lineNumber->toBe('5000555') // Config sender
        ->messageText->toBe('Text message')
        ->mobiles->toBe(['09123456789'])
        ->sendDateTime->toBeNull(); // Send now
});

it('sends text message to multiple phones', function (): void {
    fakeHttp(['status' => 1]);

    smsIrDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->mobiles->toBe(['09123456789', '09129876543']);
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['status' => 1]);

    smsIrDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->lineNumber->toBe('1000111');
});

it('sends pattern message', function (): void {
    fakeHttp(['status' => 1]);

    smsIrDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345', 'name' => 'Ali'])->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.sms.ir/v1/send/verify')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('Accept')->toBe(['application/json'])
        ->header('x-api-key')->toBe(['sms_token']); // Config token

    expect($request->data())
        ->mobile->toBe('09123456789')
        ->templateId->toBe('pattern_code')
        ->parameters->toEqual([
            ['name' => 'code', 'value' => '12345'],
            ['name' => 'name', 'value' => 'Ali'],
        ])
        ->not->toHaveKey('lineNumber'); // The verify API uses the pattern's own sender
});

it('throws an exception when sending pattern message to multiple phones', function (): void {
    fakeHttp();

    $sms = smsIrDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['code' => '12345']);

    expect(fn (): SmsIrDriver => $sms->send())
        ->toThrow(UnsupportedMultiplePhonesException::class, 'Provider "sms_ir" only supports sending to one phone number at a time for "pattern" message.');

    Http::assertNothingSent();
});

it('throws an exception when pattern variables are not key-value pairs', function (): void {
    fakeHttp();

    $sms = smsIrDriver()->pattern('09123456789', 'pattern_code', ['12345', 'Ali']);

    expect(fn (): SmsIrDriver => $sms->send())
        ->toThrow(InvalidPatternStructureException::class, 'Provider "sms_ir" only accepts pattern data as key-value pairs.');

    Http::assertNothingSent();
});

it('throws an exception when sending OTP message', function (): void {
    fakeHttp();

    $sms = smsIrDriver()->otp('09123456789', 'OTP message');

    expect(fn (): SmsIrDriver => $sms->send())
        ->toThrow(UnsupportedMethodException::class, 'Provider "sms_ir" does not support sending "otp" message, please use "pattern" method instead.');

    Http::assertNothingSent();
});

it('returns successful status when message is sent', function (SmsIrDriver $sms): void {
    fakeHttp(['status' => 1]);

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('sms_ir_messages');

it('returns failed status with error when message is not sent', function (SmsIrDriver $sms): void {
    fakeHttp(['status' => 102]);

    $sms->send();

    expect($sms)
        ->toBeFailedSms('102', 'اعتبار کافی نمیباشد'); // Sms.ir's message for status 102
})->with('sms_ir_messages');

it('throws an exception when the provider returns a server error', function (SmsIrDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): SmsIrDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('sms_ir_messages');

it('returns credit', function (): void {
    fakeHttp(['data' => 1000.4]); // The API returns the credit as a float number

    $credit = smsIrDriver()->credit();

    expect($credit)
        ->toBe(1_000); // The fake credit response, cast to int

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.sms.ir/v1/credit')
        ->method()->toBe('GET')
        ->header('Accept')->toBe(['application/json'])
        ->header('x-api-key')->toBe(['sms_token']); // Config token
});

// ------------
// Helpers
// ------------

function smsIrDriver(): SmsIrDriver
{
    return Sms::provider('sms_ir');
}
