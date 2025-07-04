<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\MeliPayamakDriver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class MeliPayamakDriverTest extends TestCase
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
            'https://rest.payamak-panel.com/api/SendSMS/end-point' => Http::response(['Value' => '123456789012345']),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
            && $request->hasHeader('Accept', 'application/json')
            && $request->url() === 'https://rest.payamak-panel.com/api/SendSMS/end-point'
            && $request->data()['username'] === 'sms_username'
            && $request->data()['password'] === 'sms_password'
            && $request->data()['key'] === 'value');
    }

    #[Test]
    public function it_sets_and_returns_the_response_status_correctly(): void
    {
        Http::fake([
            'https://rest.payamak-panel.com/api/SendSMS/success_end_point' => Http::response(['Value' => '123456789012345']), // More than 15 digits is successful status
            'https://rest.payamak-panel.com/api/SendSMS/fail_end_point' => Http::response(['Value' => '10']), // Other numbers are failed status
        ]);

        $smsDriver = $this->driver();

        // Successful response
        $this->callProtectedMethod($smsDriver, 'execute', ['success_end_point', ['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));

        // failed response
        $this->callProtectedMethod($smsDriver, 'execute', ['fail_end_point', ['key' => 'value']]);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('کاربر موردنظر فعال نمی‌باشد', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame('10', $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://rest.payamak-panel.com/api/SendSMS/*' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', ['end-point', ['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['Value' => '123456789012345'])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://rest.payamak-panel.com/api/SendSMS/SendSMS'
            && $request->data()['from'] === '4567'
            && $request->data()['to'] === '0913,0914'
            && $request['text'] === 'Text message');
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['Value' => '123456789012345'])]);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['key_1' => 'value_1', 'key_2' => 'value_2'], '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber'
            && $request->data()['to'] === '0913'
            && $request['text'] === 'value_1;value_2');
    }

    #[Test]
    public function it_throws_exception_if_we_pass_multiple_phone_numbers_to_send_with_pattern(): void
    {
        $this->expectException(UnsupportedMultiplePhonesException::class);
        $this->expectExceptionMessage('Provider "meli_payamak" only supports sending to one phone number at a time for "pattern" message.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', [], '4567']);
    }

    #[Test]
    public function it_throws_an_exception_for_sending_otp(): void
    {
        $this->expectException(UnsupportedMethodException::class);
        $this->expectExceptionMessage('Provider "meli_payamak" does not support sending "otp" message, please use "pattern" method instead.');

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['013', 'Otp message', '4567']);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): MeliPayamakDriver
    {
        Config::set('iran-sms.providers.meli_payamak.username', 'sms_username');
        Config::set('iran-sms.providers.meli_payamak.password', 'sms_password');
        Config::set('iran-sms.providers.meli_payamak.from', '123');

        return $this->app->make(MeliPayamakDriver::class);
    }
}
