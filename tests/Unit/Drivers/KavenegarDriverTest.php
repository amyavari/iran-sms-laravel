<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\KavenegarDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

final class KavenegarDriverTest extends TestCase
{
    #[Test]
    public function it_returns_sender_number_from_config(): void
    {
        $senderNumber = $this->callProtectedMethod($this->driver(), 'getDefaultSender');

        $this->assertSame('123', $senderNumber);
    }

    #[Test]
    public function it_execute_request_correctly(): void
    {
        Http::fake([
            'https://api.kavenegar.com/v1/*' => Http::response(['return' => ['status' => 200]]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        Http::assertSent(fn (Request $request) => Str::of($request->url())->startsWith('https://api.kavenegar.com/v1/')
            && $request->hasHeader('charset', 'utf-8')
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
            && Str::of($request->url())->contains('/sms_token')
            && Str::of($request->url())->endsWith('/end-point.json')
            && $request->method() === 'POST'
            && $request['key'] === 'value');
    }

    #[Test]
    public function it_sets_and_returns_the_response_status_correctly(): void
    {
        Http::fake([
            'https://api.kavenegar.com/v1/sms_token/success-end-point.json' => Http::response(['return' => ['status' => 200]]), // Follows REST API status
            'https://api.kavenegar.com/v1/sms_token/fail-end-point.json' => Http::response(['return' => ['status' => 412]]),
        ]);

        $smsDriver = $this->driver();

        // Successful response
        $this->callProtectedMethod($smsDriver, 'execute', ['success-end-point', ['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));

        // failed response
        $this->callProtectedMethod($smsDriver, 'execute', ['fail-end-point', ['key' => 'value']]);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('ارسال کننده نامعتبر است', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame(412, $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://api.kavenegar.com/v1/*' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', ['end-point', ['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['return' => ['status' => 200]])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(fn (Request $request) => Str::of($request->url())->endsWith('/sms/send.json')
            && $request['sender'] === '4567'
            && $request['message'] === 'Text message'
            && $request['receptor'] === '0913,0914');
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['return' => ['status' => 200]])]);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['token' => 'value_1', 'token2' => 'value_2'], '4567']);

        Http::assertSent(fn (Request $request) => Str::of($request->url())->endsWith('/verify/lookup.json')
            && $request['template'] === 'pattern_code'
            && $request['receptor'] === '0913'
            && $request['token'] === 'value_1'
            && $request['token2'] === 'value_2');
    }

    #[Test]
    public function it_throws_exception_if_we_pass_multiple_phone_numbers_to_send_with_pattern(): void
    {
        $this->expectException(UnsupportedMultiplePhonesException::class);
        $this->expectExceptionMessage('Provider "kavenegar" only supports sending to one phone number at a time for "pattern" message.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', [], '4567']);
    }

    #[Test]
    public function it_throws_exception_if_we_pass_non_key_value_pairs_to_send_with_pattern(): void
    {
        $this->expectException(InvalidPatternStructureException::class);
        $this->expectExceptionMessage('Provider "kavenegar" only accepts pattern data as key-value pairs.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['value_1', 'value_2', 'value_2'], '4567']);
    }

    #[Test]
    public function it_throws_an_exception_for_sending_otp(): void
    {
        $this->expectException(UnsupportedMethodException::class);
        $this->expectExceptionMessage('Provider "kavenegar" does not support sending "otp" message, please use "pattern" method instead.');

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['013', 'Otp message', '4567']);
    }

    #[Test]
    public function it_returns_credit_successfully(): void
    {
        Http::fake(['*' => Http::response(['entries' => ['remaincredit' => 1000]])]);

        $credit = $this->driver()->credit();

        $this->assertSame(1000, $credit);

        Http::assertSent(fn (Request $request) => Str::of($request->url())->startsWith('https://api.kavenegar.com/v1/')
            && $request->hasHeader('charset', 'utf-8')
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
            && Str::of($request->url())->contains('/sms_token')
            && Str::of($request->url())->endsWith('/account/info.json')
            && $request->method() === 'GET'
        );
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): KavenegarDriver
    {
        Config::set('iran-sms.providers.kavenegar.token', 'sms_token');
        Config::set('iran-sms.providers.kavenegar.from', '123');

        return $this->app->make(KavenegarDriver::class);
    }
}
