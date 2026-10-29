<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\MeliPayamakDriver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

dataset('meli_payamak_messages', [
    'Text' => fn (): MeliPayamakDriver => meliPayamakDriver()->text('09123456789', 'Text message'),
    'Pattern' => fn (): MeliPayamakDriver => meliPayamakDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345']),
]);

beforeEach(function (): void {
    Config::set('iran-sms.providers.meli_payamak.username', 'sms_username');
    Config::set('iran-sms.providers.meli_payamak.password', 'sms_password');
    Config::set('iran-sms.providers.meli_payamak.from', '5000555');
});

it('sends text message with the default sender', function (): void {
    fakeHttp(['Value' => '123456789012345']);

    meliPayamakDriver()->text('09123456789', 'Text message')->send();

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

    meliPayamakDriver()->text(['09123456789', '09129876543'], 'Text message')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->to->toBe('09123456789,09129876543');
});

it('sends text message with the given sender', function (): void {
    fakeHttp(['Value' => '123456789012345']);

    meliPayamakDriver()->text('09123456789', 'Text message')->from('1000111')->send();

    // Only what differs from the default sender test
    expect(getRecordedHttpRequest()->data())
        ->from->toBe('1000111');
});

it('sends pattern message', function (): void {
    fakeHttp(['Value' => '123456789012345']);

    meliPayamakDriver()->pattern('09123456789', 'pattern_code', ['code' => '12345', 'name' => 'Ali'])->send();

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

    $sms = meliPayamakDriver()->pattern(['09123456789', '09129876543'], 'pattern_code', ['code' => '12345']);

    expect(fn (): MeliPayamakDriver => $sms->send())
        ->toThrow(UnsupportedMultiplePhonesException::class, 'Provider "meli_payamak" only supports sending to one phone number at a time for "pattern" message.');

    Http::assertNothingSent();
});

it('throws an exception when sending OTP message', function (): void {
    fakeHttp();

    $sms = meliPayamakDriver()->otp('09123456789', 'OTP message');

    expect(fn (): MeliPayamakDriver => $sms->send())
        ->toThrow(UnsupportedMethodException::class, 'Provider "meli_payamak" does not support sending "otp" message, please use "pattern" method instead.');

    Http::assertNothingSent();
});

it('returns successful status when message is sent', function (MeliPayamakDriver $sms): void {
    fakeHttp(['Value' => '123456789012345']); // A message ID of 15 digits or more means success

    $sms->send();

    expect($sms)
        ->toBeSuccessfulSms();
})->with('meli_payamak_messages');

it('returns failed status with error when message is not sent', function (MeliPayamakDriver $sms): void {
    fakeHttp(['Value' => '2']);

    $sms->send();

    expect($sms)
        ->toBeFailedSms('2', 'اعتبار کافی نمی‌باشد'); // Meli Payamak's message for status 2
})->with('meli_payamak_messages');

it('throws an exception when the provider returns a server error', function (MeliPayamakDriver $sms): void {
    fakeHttp(status: 500);

    // Only the class is asserted, Laravel builds the message
    expect(fn (): MeliPayamakDriver => $sms->send())
        ->toThrow(RequestException::class);
})->with('meli_payamak_messages');

it('returns credit', function (): void {
    fakeHttp(['Value' => '1000']);

    $credit = meliPayamakDriver()->credit();

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

function meliPayamakDriver(): MeliPayamakDriver
{
    return Sms::provider('meli_payamak');
}
