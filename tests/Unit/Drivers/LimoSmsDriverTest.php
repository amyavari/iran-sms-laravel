<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\LimoSmsDriver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class LimoSmsDriverTest extends TestCase
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
            'https://api.limosms.com/api/end-point' => Http::response(['Success' => true, 'Message' => '']),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        Http::assertSent(fn (Request $request) => $request->hasHeader('ApiKey', 'sms_token')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->url() === 'https://api.limosms.com/api/end-point'
            && $request->method() === 'POST'
            && $request['key'] === 'value');
    }

    #[Test]
    public function it_sets_and_returns_the_successful_response_status_correctly(): void
    {
        Http::fake([
            'https://api.limosms.com/api/end-point' => Http::response([
                'Success' => true,
                'Message' => 'با موفقیت انجام شد',
            ]), // Success `true` is successful
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_sets_and_returns_the_failed_response_status_correctly(): void
    {
        Http::fake([
            'https://api.limosms.com/api/end-point' => Http::response([
                'Success' => false,
                'Message' => 'پارامتر های ورودی صحیح نمی باشد',
            ]), // All data about error is inside the response
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('پارامتر های ورودی صحیح نمی باشد', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame(0, $this->callProtectedMethod($smsDriver, 'getErrorCode')); // There is no status code in API response
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://api.limosms.com/api/end-point' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', ['end-point', ['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['Success' => true, 'Message' => ''])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.limosms.com/api/sendsms'
            && $request['SenderNumber'] === '4567'
            && $request['Message'] === 'Text message'
            && $request['MobileNumber'] === ['0913', '0914']
            && $request['SendToBlocksNumber'] === true
        );
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['Success' => true, 'Message' => ''])]);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['key_1' => 'value_1', 'key_2' => 'value_2'], '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.limosms.com/api/sendpatternmessage'
            && $request['OtpId'] === 'pattern_code'
            && $request['MobileNumber'] === '0913'
            && $request['ReplaceToken'] === ['value_1', 'value_2']
        );
    }

    #[Test]
    public function it_throws_exception_if_we_pass_multiple_phone_numbers_to_send_with_pattern(): void
    {
        $this->expectException(UnsupportedMultiplePhonesException::class);
        $this->expectExceptionMessage('Provider "limo_sms" only supports sending to one phone number at a time for "pattern" message.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', [], '4567']);
    }

    #[Test]
    public function it_throws_an_exception_for_sending_otp(): void
    {
        $this->expectException(UnsupportedMethodException::class);
        $this->expectExceptionMessage('Provider "limo_sms" does not support sending "otp" message, please use "pattern" method instead.');

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['013', 'Otp message', '4567']);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): LimoSmsDriver
    {
        Config::set('iran-sms.providers.limo_sms.token', 'sms_token');
        Config::set('iran-sms.providers.limo_sms.from', '123');

        return $this->app->make(LimoSmsDriver::class);
    }
}
