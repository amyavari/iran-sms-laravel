<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\RayganSmsDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Text and pattern use the REST API, OTP uses a separate API with a plain response
dataset('raygan_sms_rest_messages', [
    'Text' => fn (): RayganSmsDriver => rayganSmsDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): RayganSmsDriver => rayganSmsDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345']),
]);

dataset('raygan_sms_messages', [
    'Text' => fn (): RayganSmsDriver => rayganSmsDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): RayganSmsDriver => rayganSmsDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345']),
    'OTP' => fn (): RayganSmsDriver => rayganSmsDriver()->otp('09123456789', 'OTP message'),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.raygan_sms.token', 'sms_token');
    Config::set('iran-sms.providers.raygan_sms.username', 'sms_username');
    Config::set('iran-sms.providers.raygan_sms.password', 'sms_password');
    Config::set('iran-sms.providers.raygan_sms.from', '5000555');

    setTestNow('2025-12-10 18:30:10');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['Code' => 0, 'Message' => 'successful.']);

    rayganSmsDriver()->text('09123456789', 'Text message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://smspanel.trez.ir/api/smsAPI/SendMessage')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('Authorization')->toBe(['Basic c21zX3VzZXJuYW1lOnNtc19wYXNzd29yZA==']); // Base64 of the config "username:password"

    expect($request->data())
        ->PhoneNumber->toBe('5000555') // Config sender
        ->Message->toBe('Text message')
        ->Mobiles->toBe(['09123456789'])
        ->SendDateInTimeStamp->toBe(1_765_391_410); // Frozen test time as Unix timestamp

    expect(Str::isUlid($request->data()['UserGroupID']))
        ->toBeTrue(); // A new ULID on each request
});

it('sends text message to multiple phones', function (): void {
    fakeHttp(['Code' => 0, 'Message' => 'successful.']);

    rayganSmsDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->Mobiles->toBe(['09123456789', '09129876543']);
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['Code' => 0, 'Message' => 'successful.']);

    rayganSmsDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->PhoneNumber->toBe('1000111');
});

it('sends pattern message with the default sender', function (): void {
    fakeHttp(['Code' => 0, 'Message' => 'successful.']);

    rayganSmsDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345', 'name' => 'Ali'])->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://smspanel.trez.ir/api/smsApiWithPattern/SendMessage')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('Authorization')->toBe(['Basic c21zX3VzZXJuYW1lOnNtc19wYXNzd29yZA==']); // Base64 of the config "username:password"

    expect($request->data())
        ->AccessHash->toBe('sms_token') // Config token
        ->PhoneNumber->toBe('5000555') // Config sender
        ->PatternId->toBe('pattern_code')
        ->Mobiles->toBe(['09123456789'])
        ->SendDateInTimeStamp->toBe(1_765_391_410) // Frozen test time as Unix timestamp
        ->code->toBe('12345')
        ->name->toBe('Ali');

    expect(Str::isUlid($request->data()['UserGroupID']))
        ->toBeTrue(); // A new ULID on each request
});

it('sends pattern message to multiple phones', function (): void {
    fakeHttp(['Code' => 0, 'Message' => 'successful.']);

    rayganSmsDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['code' => '12345'])->send();

    // Only what differs from the pattern default sender test
    expect(getRecordedHttpRequest()->data())
        ->Mobiles->toBe(['09123456789', '09129876543']);
});

it('sends pattern message with the given sender', function (): void {
    fakeHttp(['Code' => 0, 'Message' => 'successful.']);

    rayganSmsDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345'])->from('1000111')->send();

    // Only what differs from the pattern default sender test
    expect(getRecordedHttpRequest()->data())
        ->PhoneNumber->toBe('1000111');
});

it('sends OTP message', function (): void {
    fakeHttp('2001');

    rayganSmsDriver()->otp('09123456789', 'OTP message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toStartWith('https://raygansms.com/SendMessageWithCode.ashx?') // The data is sent as query string
        ->method()->toBe('GET');

    expect($request->data())
        ->Username->toBe('sms_username') // Config username
        ->Password->toBe('sms_password') // Config password
        ->Mobile->toBe('09123456789')
        ->Message->toBe('OTP message')
        ->not->toHaveKey('PhoneNumber'); // The OTP API uses its own sender
});

it('throws an exception when pattern variables are not key-value pairs', function (): void {
    fakeHttp();

    $sms = rayganSmsDriver()->pattern('09123456789', 'pattern_code', ['12345', 'Ali']);

    expect(fn (): RayganSmsDriver => $sms->send())
        ->toThrow(InvalidPatternStructureException::class, 'Provider "raygan_sms" only accepts pattern data as key-value pairs.');

    Http::assertNothingSent();
});

it('returns successful status when REST API message is sent', function (RayganSmsDriver $sms): void {
    fakeHttp(['Code' => 0, 'Message' => 'successful.']); // Code `0` is success

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('raygan_sms_rest_messages');

it('returns failed status with error when REST API message is not sent', function (RayganSmsDriver $sms): void {
    fakeHttp(['Code' => 8, 'Message' => 'Something went wrong.']);

    $sms->send();

    expect($sms)
        ->toBeFailedSms('8', 'Something went wrong.'); // The API gives the error code and message
})->with('raygan_sms_rest_messages');

it('returns successful status when OTP message is sent', function (): void {
    fakeHttp('2001'); // A code greater than 2000 is the message ID, so success

    $sms = rayganSmsDriver()->otp('09123456789', 'OTP message')->send();

    expect($sms)
        ->toBeSuccessfulSms();
});

it('returns failed status with error when OTP message is not sent', function (): void {
    fakeHttp('8'); // A code of 2000 or less is an error code

    $sms = rayganSmsDriver()->otp('09123456789', 'OTP message')->send();

    expect($sms)
        ->toBeFailedSms('8', 'خطا با کد "8" رخ داده است.'); // Raygan SMS's message for OTP status 8
});

it('throws an exception when the provider returns a server error', function (RayganSmsDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): RayganSmsDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('raygan_sms_messages');

it('returns credit', function (): void {
    fakeHttp(['Result' => '1000']);

    $credit = rayganSmsDriver()->credit();

    expect($credit)
        ->toBe(1_000); // From the fake credit response

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://smspanel.trez.ir/api/smsAPI/GetCredit')
        ->method()->toBe('POST')
        ->header('Authorization')->toBe(['Basic c21zX3VzZXJuYW1lOnNtc19wYXNzd29yZA==']); // Base64 of the config "username:password"
});

// ------------
// Helpers
// ------------

function rayganSmsDriver(): RayganSmsDriver
{
    return Sms::provider('raygan_sms');
}
