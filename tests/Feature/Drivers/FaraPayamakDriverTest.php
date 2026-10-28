<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\FaraPayamakDriver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

dataset('fara_payamak_messages', [
    'Text' => fn (): FaraPayamakDriver => faraPayamakDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): FaraPayamakDriver => faraPayamakDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345']),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.fara_payamak.username', 'sms_username');
    Config::set('iran-sms.providers.fara_payamak.password', 'sms_password');
    Config::set('iran-sms.providers.fara_payamak.from', '5000555');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['Value' => '123456789012345']);

    faraPayamakDriver()->text('09123456789', 'Text message')->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://rest.payamak-panel.com/api/SendSMS/SendSMS')
        ->method()->toBe('POST')
        ->isForm()->toBeTrue()
        ->header('Accept')->toBe(['application/json']);

    expect($request->data())
        ->username->toBe('sms_username') // Config username
        ->password->toBe('sms_password') // Config password
        ->from->toBe('5000555') // Config sender
        ->text->toBe('Text message')
        ->to->toBe('09123456789');
});

it('sends text message to multiple phones', function (): void {
    fakeHttp(['Value' => '123456789012345']);

    faraPayamakDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->to->toBe('09123456789,09129876543');
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['Value' => '123456789012345']);

    faraPayamakDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->from->toBe('1000111');
});

it('sends pattern message', function (): void {
    fakeHttp(['Value' => '123456789012345']);

    faraPayamakDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345', 'name' => 'Ali'])->send();

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber')
        ->method()->toBe('POST')
        ->isForm()->toBeTrue()
        ->header('Accept')->toBe(['application/json']);

    expect($request->data())
        ->username->toBe('sms_username') // Config username
        ->password->toBe('sms_password') // Config password
        ->to->toBe('09123456789')
        ->bodyId->toBe('pattern_code')
        ->text->toBe('12345;Ali') // Variable values in the given order
        ->not->toHaveKey('from'); // The pattern API uses the pattern's own sender
});

it('throws an exception when sending pattern message to multiple phones', function (): void {
    fakeHttp();

    $sms = faraPayamakDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['code' => '12345']);

    expect(fn (): FaraPayamakDriver => $sms->send())
        ->toThrow(UnsupportedMultiplePhonesException::class, 'Provider "fara_payamak" only supports sending to one phone number at a time for "pattern" message.');

    Http::assertNothingSent();
});

it('throws an exception when sending OTP message', function (): void {
    fakeHttp();

    $sms = faraPayamakDriver()->otp('09123456789', 'OTP message');

    expect(fn (): FaraPayamakDriver => $sms->send())
        ->toThrow(UnsupportedMethodException::class, 'Provider "fara_payamak" does not support sending "otp" message, please use "pattern" method instead.');

    Http::assertNothingSent();
});

it('returns successful status when message is sent', function (FaraPayamakDriver $sms): void {
    fakeHttp(['Value' => '123456789012345']); // A message ID of 15 digits or more means success

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('fara_payamak_messages');

it('returns failed status with error when message is not sent', function (FaraPayamakDriver $sms): void {
    fakeHttp(['Value' => '2']);

    $sms->send();

    expect($sms)
        ->toBeFailedSms('2', 'اعتبار کافی نمی‌باشد'); // Fara Payamak's message for status 2
})->with('fara_payamak_messages');

it('throws an exception when the provider returns a server error', function (FaraPayamakDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): FaraPayamakDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('fara_payamak_messages');

it('returns credit', function (): void {
    fakeHttp(['Value' => '1000']);

    $credit = faraPayamakDriver()->credit();

    expect($credit)
        ->toBe(1_000); // From the fake credit response

    $request = getRecordedHttpRequest();

    expect($request)
        ->url()->toBe('https://rest.payamak-panel.com/api/SendSMS/GetCredit2')
        ->method()->toBe('POST')
        ->isForm()->toBeTrue()
        ->header('Accept')->toBe(['application/json']);

    expect($request->data())
        ->username->toBe('sms_username') // Config username
        ->password->toBe('sms_password'); // Config password
});

// ------------
// Helpers
// ------------

function faraPayamakDriver(): FaraPayamakDriver
{
    return Sms::provider('fara_payamak');
}
