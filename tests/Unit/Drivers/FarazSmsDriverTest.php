<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\FarazSmsDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class FarazSmsDriverTest extends TestCase
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
            'https://api.iranpayamak.com/ws/v1/end-point' => Http::response(['status' => 'success', 'data' => 0, 'messages' => null]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Api-Key', 'sms_token')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->url() === 'https://api.iranpayamak.com/ws/v1/end-point'
            && $request->method() === 'POST'
            && $request['key'] === 'value');
    }

    #[Test]
    public function it_sets_and_returns_the_successful_response_status_correctly(): void
    {
        Http::fake([
            'https://api.iranpayamak.com/ws/v1/end-point' => Http::response([
                'status' => 'success',
                'data' => 0,
                'messages' => null,
            ]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_sets_and_returns_the_failed_response_status_correctly(): void
    {
        Http::fake([
            'https://api.iranpayamak.com/ws/v1/end-point' => Http::response([
                'status' => 'error',
                'data' => 0,
                'messages' => 'Auth required',
            ]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('Auth required', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame('0', $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://api.iranpayamak.com/ws/v1/end-point' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', ['end-point', ['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => 0, 'messages' => null])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.iranpayamak.com/ws/v1/sms/simple'
            && $request['number_format'] === 'english'
            && $request['schedule'] === null
            && $request['line_number'] === '4567'
            && $request['text'] === 'Text message'
            && $request['recipients'] === ['0913', '0914']);
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => 0, 'messages' => null])]);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['key_1' => 'value_1', 'key_2' => 'value_2'], '4567']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.iranpayamak.com/ws/v1/sms/pattern'
            && $request['number_format'] === 'english'
            && $request['schedule'] === null
            && $request['line_number'] === '4567'
            && $request['code'] === 'pattern_code'
            && $request['recipient'] === '0913'
            && $request['attributes'] === ['key_1' => 'value_1', 'key_2' => 'value_2']);
    }

    #[Test]
    public function it_throws_exception_if_we_pass_multiple_phone_numbers_to_send_with_pattern(): void
    {
        $this->expectException(UnsupportedMultiplePhonesException::class);
        $this->expectExceptionMessage('Provider "faraz_sms" only supports sending to one phone number at a time for "pattern" message.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', [], '4567']);
    }

    #[Test]
    public function it_throws_exception_if_we_pass_non_key_value_pairs_to_send_with_pattern(): void
    {
        $this->expectException(InvalidPatternStructureException::class);
        $this->expectExceptionMessage('Provider "faraz_sms" only accepts pattern data as key-value pairs.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['value_1', 'value_2'], '4567']);
    }

    #[Test]
    public function it_throws_an_exception_for_sending_otp(): void
    {
        $this->expectException(UnsupportedMethodException::class);
        $this->expectExceptionMessage('Provider "faraz_sms" does not support sending "otp" message, please use "pattern" method instead.');

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['013', 'Otp message', '4567']);
    }

    #[Test]
    public function it_returns_credit_successfully(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'success',
            'message' => null,
            'data' => [
                'balanceAmount' => 1000,
                'balanceCount' => 25,
                'details' => [
                    [
                        'count' => 25,
                        'rate' => 200,
                        'amount' => 2000,
                    ],
                ],
            ],
        ])]);

        $credit = $this->driver()->credit();

        $this->assertSame(1000, $credit);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.iranpayamak.com/ws/v1/account/balance'
                && $request->method() === 'GET'
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('Api-Key', 'sms_token')
        );
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): FarazSmsDriver
    {
        Config::set('iran-sms.providers.faraz_sms.token', 'sms_token');
        Config::set('iran-sms.providers.faraz_sms.from', '123');

        return $this->app->make(FarazSmsDriver::class);
    }
}
