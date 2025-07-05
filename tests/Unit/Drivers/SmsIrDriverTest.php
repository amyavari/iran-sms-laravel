<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\SmsIrDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class SmsIrDriverTest extends TestCase
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
            'https://api.sms.ir/v1/send/end-point' => Http::response(['status' => 1]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'sms_token')
            && $request->hasHeader('Accept', 'application/json')
            && $request->url() === 'https://api.sms.ir/v1/send/end-point'
            && $request['key'] === 'value');
    }

    #[Test]
    public function it_sets_and_returns_the_response_status_correctly(): void
    {
        Http::fake([
            'https://api.sms.ir/v1/send/success_end_point' => Http::response(['status' => 1]), // 1 is successful status
            'https://api.sms.ir/v1/send/fail_end_point' => Http::response(['status' => 10]), // Other numbers are failed status
        ]);

        $smsDriver = $this->driver();

        // Successful response
        $this->callProtectedMethod($smsDriver, 'execute', ['success_end_point', ['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));

        // failed response
        $this->callProtectedMethod($smsDriver, 'execute', ['fail_end_point', ['key' => 'value']]);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('کلیدوب سرویس نامعتبر است شد', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame(10, $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://api.sms.ir/v1/send/*' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', ['end-point', ['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['status' => 1])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.sms.ir/v1/send/bulk'
            && $request['lineNumber'] === '4567'
            && $request['messageText'] === 'Text message'
            && $request['mobiles'] === ['0913', '0914']
            && $request['sendDateTime'] === null);
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['status' => 1])]);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['key_1' => 'value_1', 'key_2' => 'value_2'], '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.sms.ir/v1/send/verify'
            && $request['mobile'] === '0913'
            && $request['templateId'] === 'pattern_code'
            && $request['parameters'] === [
                [
                    'name' => 'key_1',
                    'value' => 'value_1',
                ],
                [
                    'name' => 'key_2',
                    'value' => 'value_2',
                ],
            ]);
    }

    #[Test]
    public function it_throws_exception_if_we_pass_multiple_phone_numbers_to_send_with_pattern(): void
    {
        $this->expectException(UnsupportedMultiplePhonesException::class);
        $this->expectExceptionMessage('Provider "sms_ir" only supports sending to one phone number at a time for "pattern" message.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', [], '4567']);
    }

    #[Test]
    public function it_throws_exception_if_we_pass_non_key_value_pairs_to_send_with_pattern(): void
    {
        $this->expectException(InvalidPatternStructureException::class);
        $this->expectExceptionMessage('Provider "sms_ir" only accepts pattern data as key-value pairs.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['value_1', 'value_2'], '4567']);
    }

    #[Test]
    public function it_throws_an_exception_for_sending_otp(): void
    {
        $this->expectException(UnsupportedMethodException::class);
        $this->expectExceptionMessage('Provider "sms_ir" does not support sending "otp" message, please use "pattern" method instead.');

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['013', 'Otp message', '4567']);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): SmsIrDriver
    {
        Config::set('iran-sms.providers.sms_ir.token', 'sms_token');
        Config::set('iran-sms.providers.sms_ir.from', '123');

        return $this->app->make(SmsIrDriver::class);
    }
}
