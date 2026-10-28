<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\MedianaDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

dataset('mediana_messages', [
    'Text' => fn (): MedianaDriver => medianaDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): MedianaDriver => medianaDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345']),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.mediana.token', 'sms_token');
    Config::set('iran-sms.providers.mediana.from', '5000555');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['meta' => ['status' => true, 'message' => 'Done', 'message_code' => '200-1']]);

    medianaDriver()->text('09123456789', 'Text message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://edge.ippanel.com/v1/api/send')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('Authorization')->toBe(['sms_token']); // Config token

    expect($request->data())
        ->sending_type->toBe('normal')
        ->from_number->toBe('5000555') // Config sender
        ->message->toBe('Text message')
        ->params->toEqual(['recipients' => ['09123456789']]);
});

it('sends text message to multiple phones', function (): void {
    fakeHttp(['meta' => ['status' => true, 'message' => 'Done', 'message_code' => '200-1']]);

    medianaDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->params->toEqual(['recipients' => ['09123456789', '09129876543']]);
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['meta' => ['status' => true, 'message' => 'Done', 'message_code' => '200-1']]);

    medianaDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->from_number->toBe('1000111');
});

it('sends pattern message with the default sender', function (): void {
    fakeHttp(['meta' => ['status' => true, 'message' => 'Done', 'message_code' => '200-1']]);

    medianaDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345', 'name' => 'Ali'])->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://edge.ippanel.com/v1/api/send')
        ->method()->toBe('POST')
        ->isJson()->toBeTrue()
        ->header('Authorization')->toBe(['sms_token']); // Config token

    expect($request->data())
        ->sending_type->toBe('pattern')
        ->from_number->toBe('5000555') // Config sender
        ->code->toBe('pattern_code')
        ->recipients->toBe(['09123456789'])
        ->params->toEqual(['code' => '12345', 'name' => 'Ali']);
});

it('sends pattern message to multiple phones', function (): void {
    fakeHttp(['meta' => ['status' => true, 'message' => 'Done', 'message_code' => '200-1']]);

    medianaDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['code' => '12345'])->send();

    // Only what differs from the pattern default sender test
    expect(getRecordedHttpRequest()->data())
        ->recipients->toBe(['09123456789', '09129876543']);
});

it('sends pattern message with the given sender', function (): void {
    fakeHttp(['meta' => ['status' => true, 'message' => 'Done', 'message_code' => '200-1']]);

    medianaDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345'])->from('1000111')->send();

    // Only what differs from the pattern default sender test
    expect(getRecordedHttpRequest()->data())
        ->from_number->toBe('1000111');
});

it('throws an exception when pattern variables are not key-value pairs', function (): void {
    fakeHttp();

    $sms = medianaDriver()->pattern('09123456789', 'pattern_code', ['12345', 'Ali']);

    expect(fn (): MedianaDriver => $sms->send())
        ->toThrow(InvalidPatternStructureException::class, 'Provider "mediana" only accepts pattern data as key-value pairs.');

    Http::assertNothingSent();
});

it('throws an exception when sending OTP message', function (): void {
    fakeHttp();

    $sms = medianaDriver()->otp('09123456789', 'OTP message');

    expect(fn (): MedianaDriver => $sms->send())
        ->toThrow(UnsupportedMethodException::class, 'Provider "mediana" does not support sending "otp" message, please use "pattern" method instead.');

    Http::assertNothingSent();
});

it('returns successful status when message is sent', function (MedianaDriver $sms): void {
    fakeHttp(['meta' => ['status' => true, 'message' => 'Done', 'message_code' => '200-1']]);

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('mediana_messages');

it('returns failed status with error when message is not sent', function (MedianaDriver $sms): void {
    fakeHttp(['meta' => ['status' => false, 'message' => 'Something went wrong.', 'message_code' => '400-1']]);

    $sms->send();

    expect($sms)
        ->toBeFailedSms('400-1', 'Something went wrong.'); // The API gives the error code and message
})->with('mediana_messages');

it('throws an exception when the provider returns a server error', function (MedianaDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): MedianaDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('mediana_messages');

it('returns credit', function (): void {
    fakeHttp(['data' => ['credit' => 1000.2354]]); // The API returns the credit as a float number

    $credit = medianaDriver()->credit();

    expect($credit)
        ->toBe(1_000); // The fake credit response, cast to int

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://edge.ippanel.com/v1/api/payment/credit/mine')
        ->method()->toBe('GET')
        ->header('Authorization')->toBe(['sms_token']); // Config token
});

// ------------
// Helpers
// ------------

function medianaDriver(): MedianaDriver
{
    return Sms::provider('mediana');
}
