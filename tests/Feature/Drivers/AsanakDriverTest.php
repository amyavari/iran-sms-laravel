<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\AsanakDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

dataset('asanak_messages', [
    'Text' => fn (): AsanakDriver => asanakDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): AsanakDriver => asanakDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345']),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.asanak.username', 'sms_username');
    Config::set('iran-sms.providers.asanak.password', 'sms_password');
    Config::set('iran-sms.providers.asanak.from', '5000555');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['meta' => ['status' => 200, 'message' => 'success']]);

    asanakDriver()->text('09123456789', 'Text message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://sms.asanak.ir/webservice/v2rest/sendsms')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('Accept')->toBe(['application/json']);

    expect($request->data())
        ->username->toBe('sms_username') // Config username
        ->password->toBe('sms_password') // Config password
        ->source->toBe('5000555') // Config sender
        ->message->toBe('Text message')
        ->destination->toBe('09123456789')
        ->send_to_blacklist->toBe(1); // Send to all numbers
});

it('sends text message to multiple phones', function (): void {
    fakeHttp(['meta' => ['status' => 200, 'message' => 'success']]);

    asanakDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->destination->toBe('09123456789,09129876543');
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['meta' => ['status' => 200, 'message' => 'success']]);

    asanakDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->source->toBe('1000111');
});

it('sends pattern message', function (): void {
    fakeHttp(['meta' => ['status' => 200, 'message' => 'success']]);

    asanakDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345', 'name' => 'Ali'])->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://sms.asanak.ir/webservice/v2rest/template')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('Accept')->toBe(['application/json']);

    expect($request->data())
        ->username->toBe('sms_username') // Config username
        ->password->toBe('sms_password') // Config password
        ->template_id->toBe('pattern_code')
        ->destination->toBe('09123456789')
        ->parameters->toEqual(['code' => '12345', 'name' => 'Ali'])
        ->send_to_blacklist->toBe(1) // Send to all numbers
        ->not->toHaveKey('source'); // The template API uses the pattern's own sender
});

it('throws an exception when sending pattern message to multiple phones', function (): void {
    fakeHttp();

    $sms = asanakDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['code' => '12345']);

    expect(fn (): AsanakDriver => $sms->send())
        ->toThrow(UnsupportedMultiplePhonesException::class, 'Provider "asanak" only supports sending to one phone number at a time for "pattern" message.');

    Http::assertNothingSent();
});

it('throws an exception when pattern variables are not key-value pairs', function (): void {
    fakeHttp();

    $sms = asanakDriver()->pattern('09123456789', 'pattern_code', ['12345', 'Ali']);

    expect(fn (): AsanakDriver => $sms->send())
        ->toThrow(InvalidPatternStructureException::class, 'Provider "asanak" only accepts pattern data as key-value pairs.');

    Http::assertNothingSent();
});

it('throws an exception when sending OTP message', function (): void {
    fakeHttp();

    $sms = asanakDriver()->otp('09123456789', 'OTP message');

    expect(fn (): AsanakDriver => $sms->send())
        ->toThrow(UnsupportedMethodException::class, 'Provider "asanak" does not support sending "otp" message, please use "pattern" method instead.');

    Http::assertNothingSent();
});

it('returns successful status when message is sent', function (AsanakDriver $sms): void {
    fakeHttp(['meta' => ['status' => 200, 'message' => 'success']]);

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('asanak_messages');

it('returns failed status with error when message is not sent', function (AsanakDriver $sms): void {
    fakeHttp(['meta' => ['status' => 1006, 'message' => 'Insufficient credit']]);

    $sms->send();

    expect($sms)
        ->toBeFailedSms('1006', 'خطای مربوط به نداشتن اعتبار کافی برای ارسال'); // Asanak's message for status 1006
})->with('asanak_messages');

it('throws an exception when the provider returns a server error', function (AsanakDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): AsanakDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('asanak_messages');

it('returns credit', function (): void {
    fakeHttp(['meta' => ['status' => 200, 'message' => 'success'], 'data' => ['credit' => 1_000]]);

    $credit = asanakDriver()->credit();

    expect($credit)
        ->toBe(1_000); // From the fake credit response

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://sms.asanak.ir/webservice/v2rest/getrialcredit')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('Accept')->toBe(['application/json']);

    expect($request->data())
        ->username->toBe('sms_username') // Config username
        ->password->toBe('sms_password'); // Config password
});

// ------------
// Helpers
// ------------

function asanakDriver(): AsanakDriver
{
    return Sms::provider('asanak');
}
