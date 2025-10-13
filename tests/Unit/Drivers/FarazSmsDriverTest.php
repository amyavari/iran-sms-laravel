<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\FarazSmsDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
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
            'https://edge.ippanel.com/v1/api/send' => Http::response(['meta' => ['status' => true, 'message' => '', 'message_code' => '']]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', [['key' => 'value']]);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'sms_token')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->url() === 'https://edge.ippanel.com/v1/api/send'
            && $request->method() === 'POST'
            && $request['key'] === 'value');
    }

    #[Test]
    public function it_sets_and_returns_the_successful_response_status_correctly(): void
    {
        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response([
                'meta' => [
                    'status' => true,
                    'message' => 'انجام شد',
                    'message_code' => '200-1',
                ],
            ]), // Statue `true` is successful
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', [['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_sets_and_returns_the_failed_response_status_correctly(): void
    {
        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response([
                'meta' => [
                    'status' => false,
                    'message' => 'Something went wrong.',
                    'message_code' => '400-1',
                ], // All data about error is inside the response
            ]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', [['key' => 'value']]);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('Something went wrong.', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame('400-1', $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', [['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['meta' => ['status' => true, 'message' => '', 'message_code' => '']])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(fn (Request $request) => $request['sending_type'] === 'normal'
            && $request['from_number'] === '4567'
            && $request['message'] === 'Text message'
            && $request['params'] === ['recipients' => ['0913', '0914']]);
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['meta' => ['status' => true, 'message' => '', 'message_code' => '']])]);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', ['key_1' => 'value_1', 'key_2' => 'value_2'], '4567']);

        Http::assertSent(fn (Request $request) => $request['sending_type'] === 'pattern'
            && $request['from_number'] === '4567'
            && $request['code'] === 'pattern_code'
            && $request['recipients'] === ['0913', '0914']
            && $request['params'] === ['key_1' => 'value_1', 'key_2' => 'value_2']);
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
        Http::fake(['*' => Http::response(['data' => ['credit' => 1000.2354]])]);

        $credit = $this->driver()->credit();

        $this->assertSame(1000, $credit);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://edge.ippanel.com/v1/api/payment/credit/mine'
                && $request->method() === 'GET'
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->hasHeader('Authorization', 'sms_token')
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
