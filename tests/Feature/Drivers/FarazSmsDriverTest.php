<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\FarazSmsDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

dataset('faraz_sms_messages', [
    'Text' => fn (): FarazSmsDriver => farazSmsDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): FarazSmsDriver => farazSmsDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345']),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.faraz_sms.token', 'sms_token');
    Config::set('iran-sms.providers.faraz_sms.from', '5000555');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['status' => 'success', 'data' => 0, 'messages' => null]);

    farazSmsDriver()->text('09123456789', 'Text message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.iranpayamak.com/ws/v1/sms/simple')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('Api-Key')->toBe(['sms_token']); // Config token

    expect($request->data())
        ->number_format->toBe('english')
        ->line_number->toBe('5000555') // Config sender
        ->text->toBe('Text message')
        ->recipients->toBe(['09123456789'])
        ->schedule->toBeNull(); // Send now
});

it('sends text message to multiple phones', function (): void {
    fakeHttp(['status' => 'success', 'data' => 0, 'messages' => null]);

    farazSmsDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->recipients->toBe(['09123456789', '09129876543']);
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['status' => 'success', 'data' => 0, 'messages' => null]);

    farazSmsDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->line_number->toBe('1000111');
});

it('sends pattern message with the default sender', function (): void {
    fakeHttp(['status' => 'success', 'data' => 0, 'messages' => null]);

    farazSmsDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345', 'name' => 'Ali'])->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.iranpayamak.com/ws/v1/sms/pattern')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('Api-Key')->toBe(['sms_token']); // Config token

    expect($request->data())
        ->number_format->toBe('english')
        ->line_number->toBe('5000555') // Config sender
        ->code->toBe('pattern_code')
        ->recipient->toBe('09123456789')
        ->attributes->toEqual(['code' => '12345', 'name' => 'Ali'])
        ->schedule->toBeNull(); // Send now
});

it('sends pattern message with the given sender', function (): void {
    fakeHttp(['status' => 'success', 'data' => 0, 'messages' => null]);

    farazSmsDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345'])->from('1000111')->send();

    // Only what differs from the pattern default sender test
    expect(getRecordedHttpRequest()->data())
        ->line_number->toBe('1000111');
});

it('throws an exception when sending pattern message to multiple phones', function (): void {
    fakeHttp();

    $sms = farazSmsDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['code' => '12345']);

    expect(fn (): FarazSmsDriver => $sms->send())
        ->toThrow(UnsupportedMultiplePhonesException::class, 'Provider "faraz_sms" only supports sending to one phone number at a time for "pattern" message.');

    Http::assertNothingSent();
});

it('throws an exception when pattern variables are not key-value pairs', function (): void {
    fakeHttp();

    $sms = farazSmsDriver()->pattern('09123456789', 'pattern_code', ['12345', 'Ali']);

    expect(fn (): FarazSmsDriver => $sms->send())
        ->toThrow(InvalidPatternStructureException::class, 'Provider "faraz_sms" only accepts pattern data as key-value pairs.');

    Http::assertNothingSent();
});

it('throws an exception when sending OTP message', function (): void {
    fakeHttp();

    $sms = farazSmsDriver()->otp('09123456789', 'OTP message');

    expect(fn (): FarazSmsDriver => $sms->send())
        ->toThrow(UnsupportedMethodException::class, 'Provider "faraz_sms" does not support sending "otp" message, please use "pattern" method instead.');

    Http::assertNothingSent();
});

it('returns successful status when message is sent', function (FarazSmsDriver $sms): void {
    fakeHttp(['status' => 'success', 'data' => 0, 'messages' => null]);

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('faraz_sms_messages');

it('returns failed status with error when message is not sent', function (FarazSmsDriver $sms): void {
    fakeHttp(['status' => 'error', 'data' => 0, 'messages' => 'Auth required']);

    $sms->send();

    expect($sms)
        ->toBeFailedSms('0', 'Auth required'); // The API gives no error code, the driver uses `0`. The message is the API's
})->with('faraz_sms_messages');

it('throws an exception when the provider returns a server error', function (FarazSmsDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): FarazSmsDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('faraz_sms_messages');

it('returns credit', function (): void {
    fakeHttp(['status' => 'success', 'data' => ['balanceAmount' => 1_000], 'messages' => null]);

    $credit = farazSmsDriver()->credit();

    expect($credit)
        ->toBe(1_000); // From the fake credit response

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://api.iranpayamak.com/ws/v1/account/balance')
        ->method()->toBe('GET')
        ->header('Api-Key')->toBe(['sms_token']); // Config token
});

// ------------
// Helpers
// ------------

function farazSmsDriver(): FarazSmsDriver
{
    return Sms::provider('faraz_sms');
}
