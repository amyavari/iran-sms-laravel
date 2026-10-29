<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\WebOneDriver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

dataset('web_one_messages', [
    'Text' => fn (): WebOneDriver => webOneDriver()->text('09123456789', 'Text message'),
    'OTP' => fn (): WebOneDriver => webOneDriver()->otp('09123456789', 'OTP message'),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.web_one.token', 'sms_token');
    Config::set('iran-sms.providers.web_one.from', '5000555');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['succeeded' => true, 'resultCode' => 0]);

    webOneDriver()->text('09123456789', 'Text message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.payamakapi.ir/api/v1/SMS/Send')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('X-API-KEY')->toBe(['sms_token']); // Config token

    expect($request->data())
        ->From->toBe('5000555') // Config sender
        ->ToNumbers->toBe(['09123456789'])
        ->Content->toBe('Text message');
});

it('sends text message to multiple phones', function (): void {
    fakeHttp(['succeeded' => true, 'resultCode' => 0]);

    webOneDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->ToNumbers->toBe(['09123456789', '09129876543']);
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['succeeded' => true, 'resultCode' => 0]);

    webOneDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->From->toBe('1000111');
});

it('sends OTP message', function (): void {
    fakeHttp(['succeeded' => true, 'resultCode' => 0]);

    webOneDriver()->otp('09123456789', 'OTP message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.payamakapi.ir/api/v1/SMS/SmartOTP')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('X-API-KEY')->toBe(['sms_token']); // Config token

    expect($request->data())
        ->ToNumber->toBe('09123456789')
        ->Content->toBe('OTP message')
        ->not->toHaveKey('From'); // The OTP API uses its own sender
});

it('throws an exception when sending pattern message', function (): void {
    fakeHttp();

    $sms = webOneDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345']);

    expect(fn (): WebOneDriver => $sms->send())
        ->toThrow(UnsupportedMethodException::class, 'Provider "web_one" does not support sending "pattern" message, please use "text" method instead.');

    Http::assertNothingSent();
});

it('returns successful status when message is sent', function (WebOneDriver $sms): void {
    fakeHttp(['succeeded' => true, 'resultCode' => 0]);

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('web_one_messages');

it('returns failed status with error when message is not sent', function (WebOneDriver $sms): void {
    fakeHttp(['succeeded' => false, 'resultCode' => 8]);

    $sms->send();

    expect($sms)
        ->toBeFailedSms('8', 'اعتبار كافي نيست'); // Web One's message for result code 8
})->with('web_one_messages');

it('throws an exception when the provider returns a server error', function (WebOneDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): WebOneDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('web_one_messages');

it('returns credit', function (): void {
    fakeHttp('1000.4'); // The API returns the credit as a plain body with a float number

    $credit = webOneDriver()->credit();

    expect($credit)
        ->toBe(1_000); // The fake credit response, cast to int

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.payamakapi.ir/api/v1/SMS/GetCredit')
        ->method()->toBe('GET')
        ->header('X-API-KEY')->toBe(['sms_token']); // Config token
});

// ------------
// Helpers
// ------------

function webOneDriver(): WebOneDriver
{
    return Sms::provider('web_one');
}
