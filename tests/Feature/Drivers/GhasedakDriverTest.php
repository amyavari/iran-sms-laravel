<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\GhasedakDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

dataset('ghasedak_messages', [
    'Text' => fn (): GhasedakDriver => ghasedakDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): GhasedakDriver => ghasedakDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345']),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.ghasedak.token', 'sms_token');
    Config::set('iran-sms.providers.ghasedak.from', '5000555');

    setTestNow('2025-12-10 18:30:10');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['IsSuccess' => true, 'Message' => '', 'StatusCode' => 200]);

    ghasedakDriver()->text('09123456789', 'Text message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://gateway.ghasedak.me/rest/api/v1/WebService/SendBulkSMS')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('ApiKey')->toBe(['sms_token']); // Config token

    expect($request->data())
        ->lineNumber->toBe('5000555') // Config sender
        ->message->toBe('Text message')
        ->receptors->toBe(['09123456789'])
        ->clientReferenceId->toBeNull()
        ->isVoice->toBeFalse()
        ->udh->toBeFalse()
        ->sendDate->toBe('2025-12-10T18:30:10.000000Z'); // Frozen test time, always in UTC
});

it('sends text message to multiple phones', function (): void {
    fakeHttp(['IsSuccess' => true, 'Message' => '', 'StatusCode' => 200]);

    ghasedakDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->receptors->toBe(['09123456789', '09129876543']);
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['IsSuccess' => true, 'Message' => '', 'StatusCode' => 200]);

    ghasedakDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->lineNumber->toBe('1000111');
});

it('sends pattern message', function (): void {
    fakeHttp(['IsSuccess' => true, 'Message' => '', 'StatusCode' => 200]);

    ghasedakDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345', 'name' => 'Ali'])->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://gateway.ghasedak.me/rest/api/v1/WebService/SendOtpSMS')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('ApiKey')->toBe(['sms_token']); // Config token

    expect($request->data())
        ->templateName->toBe('pattern_code')
        ->receptors->toEqual([
            ['mobile' => '09123456789', 'clientReferenceId' => null],
        ])
        ->inputs->toEqual([
            ['param' => 'code', 'value' => '12345'],
            ['param' => 'name', 'value' => 'Ali'],
        ])
        ->udh->toBeFalse()
        ->sendDate->toBe('2025-12-10T18:30:10.000000Z') // Frozen test time, always in UTC
        ->not->toHaveKey('lineNumber'); // The pattern API uses the pattern's own sender
});

it('sends pattern message to multiple phones', function (): void {
    fakeHttp(['IsSuccess' => true, 'Message' => '', 'StatusCode' => 200]);

    ghasedakDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['code' => '12345'])->send();

    // Only what differs from the pattern test
    expect(getRecordedHttpRequest()->data())
        ->receptors->toEqual([
            ['mobile' => '09123456789', 'clientReferenceId' => null],
            ['mobile' => '09129876543', 'clientReferenceId' => null],
        ]);
});

it('throws an exception when pattern variables are not key-value pairs', function (): void {
    fakeHttp();

    $sms = ghasedakDriver()->pattern('09123456789', 'pattern_code', ['12345', 'Ali']);

    expect(fn (): GhasedakDriver => $sms->send())
        ->toThrow(InvalidPatternStructureException::class, 'Provider "ghasedak" only accepts pattern data as key-value pairs.');

    Http::assertNothingSent();
});

it('throws an exception when sending OTP message', function (): void {
    fakeHttp();

    $sms = ghasedakDriver()->otp('09123456789', 'OTP message');

    expect(fn (): GhasedakDriver => $sms->send())
        ->toThrow(UnsupportedMethodException::class, 'Provider "ghasedak" does not support sending "otp" message, please use "pattern" method instead.');

    Http::assertNothingSent();
});

it('returns successful status when message is sent', function (GhasedakDriver $sms): void {
    fakeHttp(['IsSuccess' => true, 'Message' => '', 'StatusCode' => 200]);

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('ghasedak_messages');

it('returns failed status with error when message is not sent', function (GhasedakDriver $sms): void {
    fakeHttp(['IsSuccess' => false, 'Message' => 'پارامتر های ورودی صحیح نمی باشد', 'StatusCode' => 400]);

    $sms->send();

    expect($sms)
        ->toBeFailedSms('400', 'پارامتر های ورودی صحیح نمی باشد'); // The API gives the error code and message
})->with('ghasedak_messages');

it('throws an exception when the provider returns a server error', function (GhasedakDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): GhasedakDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('ghasedak_messages');

it('returns credit', function (): void {
    fakeHttp(['Data' => ['Credit' => 1_000]]);

    $credit = ghasedakDriver()->credit();

    expect($credit)
        ->toBe(1_000); // From the fake credit response

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://gateway.ghasedak.me/rest/api/v1/WebService/GetAccountInformation')
        ->method()->toBe('GET')
        ->header('ApiKey')->toBe(['sms_token']); // Config token
});

// ------------
// Helpers
// ------------

function ghasedakDriver(): GhasedakDriver
{
    return Sms::provider('ghasedak');
}
