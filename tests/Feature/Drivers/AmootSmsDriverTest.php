<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\AmootSmsDriver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

dataset('amoot_sms_messages', [
    'Text' => fn (): AmootSmsDriver => amootSmsDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): AmootSmsDriver => amootSmsDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345']),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.amoot_sms.token', 'sms_token');
    Config::set('iran-sms.providers.amoot_sms.from', '5000555');

    setTestNow('2025-12-10 18:30:10');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['Status' => 'Success']);

    amootSmsDriver()->text('09123456789', 'Text message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toStartWith('https://portal.amootsms.com/rest/SendSimple?') // The data is sent as query string
        ->method()->toBe('GET');

    expect($request->data())
        ->Token->toBe('sms_token') // Config token
        ->LineNumber->toBe('5000555') // Config sender
        ->SMSMessageText->toBe('Text message')
        ->Mobiles->toBe('09123456789')
        ->SendDateTime->toBe('2025-12-10T22:00:10+03:30'); // Frozen test time in Tehran time
});

it('sends text message to multiple phones', function (): void {
    fakeHttp(['Status' => 'Success']);

    amootSmsDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->Mobiles->toBe('09123456789,09129876543');
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['Status' => 'Success']);

    amootSmsDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->LineNumber->toBe('1000111');
});

it('sends pattern message', function (): void {
    fakeHttp(['Status' => 'Success']);

    amootSmsDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345', 'name' => 'Ali'])->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toStartWith('https://portal.amootsms.com/rest/SendWithPattern?') // The data is sent as query string
        ->method()->toBe('GET');

    expect($request->data())
        ->Token->toBe('sms_token') // Config token
        ->Mobile->toBe('09123456789')
        ->PatternCodeID->toBe('pattern_code')
        ->PatternValues->toBe('12345,Ali') // Variable values in the given order
        ->not->toHaveKey('LineNumber'); // The pattern API uses the pattern's own sender
});

it('throws an exception when sending pattern message to multiple phones', function (): void {
    fakeHttp();

    $sms = amootSmsDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['code' => '12345']);

    expect(fn (): AmootSmsDriver => $sms->send())
        ->toThrow(UnsupportedMultiplePhonesException::class, 'Provider "amoot_sms" only supports sending to one phone number at a time for "pattern" message.');

    Http::assertNothingSent();
});

it('throws an exception when sending OTP message', function (): void {
    fakeHttp();

    $sms = amootSmsDriver()->otp('09123456789', 'OTP message');

    expect(fn (): AmootSmsDriver => $sms->send())
        ->toThrow(UnsupportedMethodException::class, 'Provider "amoot_sms" does not support sending "otp" message, please use "pattern" method instead.');

    Http::assertNothingSent();
});

it('returns successful status when message is sent', function (AmootSmsDriver $sms): void {
    fakeHttp(['Status' => 'Success']);

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('amoot_sms_messages');

it('returns failed status with error when message is not sent', function (AmootSmsDriver $sms): void {
    fakeHttp(['Status' => 'CreditNotEnough']);

    $sms->send();

    expect($sms)
        ->toBeFailedSms('', 'CreditNotEnough'); // The API gives no error code, the message is the API status
})->with('amoot_sms_messages');

it('throws an exception when the provider returns a server error', function (AmootSmsDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): AmootSmsDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('amoot_sms_messages');

it('returns credit', function (): void {
    fakeHttp(['Status' => 'Success', 'RemaindCredit' => 1_000]);

    $credit = amootSmsDriver()->credit();

    expect($credit)
        ->toBe(1_000); // From the fake credit response

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toStartWith('https://portal.amootsms.com/rest/AccountStatus?') // The data is sent as query string
        ->method()->toBe('GET');

    expect($request->data())
        ->Token->toBe('sms_token'); // Config token
});

// ------------
// Helpers
// ------------

function amootSmsDriver(): AmootSmsDriver
{
    return Sms::provider('amoot_sms');
}
