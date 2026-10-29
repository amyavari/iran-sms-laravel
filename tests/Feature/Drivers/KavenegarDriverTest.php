<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\KavenegarDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

dataset('kavenegar_messages', [
    'Text' => fn (): KavenegarDriver => kavenegarDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): KavenegarDriver => kavenegarDriver()->pattern('09123456789', 'pattern_code', ['token' => 'value_1']),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.kavenegar.token', 'sms_token');
    Config::set('iran-sms.providers.kavenegar.from', '5000555');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['return' => ['status' => 200]]);

    kavenegarDriver()->text('09123456789', 'Text message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.kavenegar.com/v1/sms_token/sms/send.json') // The config token is part of the URL
        ->method()->toBe('POST')
        ->isForm()->toBeTrue()
        ->header('Accept')->toBe(['application/json'])
        ->header('charset')->toBe(['utf-8']);

    expect($request->data())
        ->sender->toBe('5000555') // Config sender
        ->message->toBe('Text message')
        ->receptor->toBe('09123456789');
});

it('sends text message to multiple phones', function (): void {
    fakeHttp(['return' => ['status' => 200]]);

    kavenegarDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->receptor->toBe('09123456789,09129876543');
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['return' => ['status' => 200]]);

    kavenegarDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->sender->toBe('1000111');
});

it('sends pattern message', function (): void {
    fakeHttp(['return' => ['status' => 200]]);

    kavenegarDriver()->pattern('09123456789', 'pattern_code', ['token' => 'value_1', 'token2' => 'value_2'])->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.kavenegar.com/v1/sms_token/verify/lookup.json') // The config token is part of the URL
        ->method()->toBe('POST')
        ->isForm()->toBeTrue()
        ->header('Accept')->toBe(['application/json'])
        ->header('charset')->toBe(['utf-8']);

    expect($request->data())
        ->receptor->toBe('09123456789')
        ->template->toBe('pattern_code')
        ->token->toBe('value_1')
        ->token2->toBe('value_2')
        ->not->toHaveKey('sender'); // The lookup API uses the pattern's own sender
});

it('throws an exception when sending pattern message to multiple phones', function (): void {
    fakeHttp();

    $sms = kavenegarDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['token' => 'value_1']);

    expect(fn (): KavenegarDriver => $sms->send())
        ->toThrow(UnsupportedMultiplePhonesException::class, 'Provider "kavenegar" only supports sending to one phone number at a time for "pattern" message.');

    Http::assertNothingSent();
});

it('throws an exception when pattern variables are not key-value pairs', function (): void {
    fakeHttp();

    $sms = kavenegarDriver()->pattern('09123456789', 'pattern_code', ['value_1', 'value_2']);

    expect(fn (): KavenegarDriver => $sms->send())
        ->toThrow(InvalidPatternStructureException::class, 'Provider "kavenegar" only accepts pattern data as key-value pairs.');

    Http::assertNothingSent();
});

it('throws an exception when sending OTP message', function (): void {
    fakeHttp();

    $sms = kavenegarDriver()->otp('09123456789', 'OTP message');

    expect(fn (): KavenegarDriver => $sms->send())
        ->toThrow(UnsupportedMethodException::class, 'Provider "kavenegar" does not support sending "otp" message, please use "pattern" method instead.');

    Http::assertNothingSent();
});

it('returns successful status when message is sent', function (KavenegarDriver $sms): void {
    fakeHttp(['return' => ['status' => 200]]);

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('kavenegar_messages');

it('returns failed status with error when message is not sent', function (KavenegarDriver $sms): void {
    fakeHttp(['return' => ['status' => 412]]);

    $sms->send();

    expect($sms)
        ->toBeFailedSms('412', 'ارسال کننده نامعتبر است'); // Kavenegar's message for status 412
})->with('kavenegar_messages');

it('throws an exception when the provider returns a server error', function (KavenegarDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): KavenegarDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('kavenegar_messages');

it('returns credit', function (): void {
    fakeHttp(['entries' => ['remaincredit' => 1_000]]);

    $credit = kavenegarDriver()->credit();

    expect($credit)
        ->toBe(1_000); // From the fake credit response

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.kavenegar.com/v1/sms_token/account/info.json') // The config token is part of the URL
        ->method()->toBe('GET')
        ->header('Accept')->toBe(['application/json'])
        ->header('charset')->toBe(['utf-8']);
});

// ------------
// Helpers
// ------------

function kavenegarDriver(): KavenegarDriver
{
    return Sms::provider('kavenegar');
}
