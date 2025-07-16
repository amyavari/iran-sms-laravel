<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\WebOneDriver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class WebOneDriverTest extends TestCase
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
            'https://api.payamakapi.ir/api/v1/end-point' => Http::response(['Succeeded' => true, 'resultCode' => 0]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-API-KEY', 'sms_token')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->url() === 'https://api.payamakapi.ir/api/v1/end-point'
            && $request['key'] === 'value');
    }

    #[Test]
    public function it_sets_and_returns_the_successful_response_status_correctly(): void
    {
        Http::fake([
            'https://api.payamakapi.ir/api/v1/end-point' => Http::response(['Succeeded' => true, 'resultCode' => 0]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_sets_and_returns_the_failed_response_status_correctly(): void
    {
        Http::fake([
            'https://api.payamakapi.ir/api/v1/end-point' => Http::response(['Succeeded' => false, 'resultCode' => 1]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('نام كاربر يا كلمه عبور نامعتبر مي باشد', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame(1, $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://api.payamakapi.ir/api/v1/end-point' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', ['end-point', ['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['Succeeded' => false, 'resultCode' => 1])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.payamakapi.ir/api/v1/SMS/Send'
            && $request['From'] === '4567'
            && $request['ToNumbers'] === ['0913', '0914']
            && $request['Content'] === 'Text message');
    }

    #[Test]
    public function it_sends_otp_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['Succeeded' => false, 'resultCode' => 1])]);

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['0913', 'Otp message', '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.payamakapi.ir/api/v1/SMS/SmartOTP'
            && $request['ToNumber'] === '0913'
            && $request['Content'] === 'Otp message');
    }

    #[Test]
    public function it_throws_an_exception_for_sending_pattern(): void
    {
        $this->expectException(UnsupportedMethodException::class);
        $this->expectExceptionMessage('Provider "web_one" does not support sending "pattern" message, please use "text" method instead.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', ['key_1' => 'value_1', 'key_2' => 'value_2'], '4567']);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): WebOneDriver
    {
        Config::set('iran-sms.providers.web_one.token', 'sms_token');
        Config::set('iran-sms.providers.web_one.from', '123');

        return $this->app->make(WebOneDriver::class);
    }
}
