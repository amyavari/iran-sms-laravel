<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\BehinPayamDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

dataset('behin_payam_messages', [
    'Text' => fn (): BehinPayamDriver => behinPayamDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): BehinPayamDriver => behinPayamDriver()->pattern('09123456789', 'pattern_code', ['token1' => 'value_1', 'token2' => 'value_2', 'token3' => 'value_3']),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.behin_payam.token', 'sms_token');
    Config::set('iran-sms.providers.behin_payam.from', '5000555');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['id' => 123]);

    behinPayamDriver()->text('09123456789', 'Text message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toStartWith('https://api.sms-webservice.com/api/V3/Send?') // The data is sent as query string
        ->method()->toBe('GET')
        ->header('Accept')->toBe(['application/json']);

    expect($request->data())
        ->ApiKey->toBe('sms_token') // Config token
        ->Sender->toBe('5000555') // Config sender
        ->Text->toBe('Text message')
        ->Recipients->toBe(['09123456789']);
});

it('sends text message to multiple phones', function (): void {
    fakeHttp(['id' => 123]);

    behinPayamDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->Recipients->toBe(['09123456789', '09129876543']);
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['id' => 123]);

    behinPayamDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->Sender->toBe('1000111');
});

it('sends pattern message', function (): void {
    fakeHttp(['id' => 123]);

    behinPayamDriver()->pattern('09123456789', 'pattern_code', ['token1' => 'value_1', 'token2' => 'value_2', 'token3' => 'value_3'])->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toStartWith('https://api.sms-webservice.com/api/V3/SendTokenSingle?') // The data is sent as query string
        ->method()->toBe('GET')
        ->header('Accept')->toBe(['application/json']);

    expect($request->data())
        ->ApiKey->toBe('sms_token') // Config token
        ->Destination->toBe('09123456789')
        ->TemplateKey->toBe('pattern_code')
        ->token1->toBe('value_1')
        ->token2->toBe('value_2')
        ->token3->toBe('value_3')
        ->not->toHaveKey('Sender'); // The pattern API uses the pattern's own sender
});

it('throws an exception when sending pattern message to multiple phones', function (): void {
    fakeHttp();

    $sms = behinPayamDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['token1' => 'value_1', 'token2' => 'value_2', 'token3' => 'value_3']);

    expect(fn (): BehinPayamDriver => $sms->send())
        ->toThrow(UnsupportedMultiplePhonesException::class, 'Provider "behin_payam" only supports sending to one phone number at a time for "pattern" message.');

    Http::assertNothingSent();
});

it('throws an exception when pattern variables are not exactly 3 items', function (array $variables): void {
    fakeHttp();

    $sms = behinPayamDriver()->pattern('09123456789', 'pattern_code', $variables);

    expect(fn (): BehinPayamDriver => $sms->send())
        ->toThrow(InvalidPatternStructureException::class, 'Provider "behin_payam" only accepts pattern data with exactly 3 items.');

    Http::assertNothingSent();
})->with([
    'Less items' => ['variables' => ['token1' => 'value_1', 'token2' => 'value_2']],
    'More items' => ['variables' => ['token1' => 'value_1', 'token2' => 'value_2', 'token3' => 'value_3', 'token4' => 'value_4']],
]);

it('throws an exception when pattern variables are not key-value pairs', function (): void {
    fakeHttp();

    $sms = behinPayamDriver()->pattern('09123456789', 'pattern_code', ['value_1', 'value_2', 'value_3']);

    expect(fn (): BehinPayamDriver => $sms->send())
        ->toThrow(InvalidPatternStructureException::class, 'Provider "behin_payam" only accepts pattern data as key-value pairs.');

    Http::assertNothingSent();
});

it('throws an exception when sending OTP message', function (): void {
    fakeHttp();

    $sms = behinPayamDriver()->otp('09123456789', 'OTP message');

    expect(fn (): BehinPayamDriver => $sms->send())
        ->toThrow(UnsupportedMethodException::class, 'Provider "behin_payam" does not support sending "otp" message, please use "pattern" method instead.');

    Http::assertNothingSent();
});

it('returns successful status when message is sent', function (BehinPayamDriver $sms): void {
    fakeHttp(['id' => 123]); // The API returns the message ID

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('behin_payam_messages');

it('throws an exception when the provider returns a server error', function (BehinPayamDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): BehinPayamDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('behin_payam_messages');

it('returns credit', function (): void {
    fakeHttp(['Credit' => 1_000]);

    $credit = behinPayamDriver()->credit();

    expect($credit)
        ->toBe(1_000); // From the fake credit response

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.sms-webservice.com/api/V3/AccountInfo')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue();

    expect($request->data())
        ->ApiKey->toBe('sms_token'); // Config token
});

// ------------
// Helpers
// ------------

function behinPayamDriver(): BehinPayamDriver
{
    return Sms::provider('behin_payam');
}
