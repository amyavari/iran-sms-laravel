<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\AsanakDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class AsanakDriverTest extends TestCase
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
            'https://sms.asanak.ir/webservice/v2rest/end-point' => Http::response(['meta' => ['status' => 200, 'message' => '']]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://sms.asanak.ir/webservice/v2rest/end-point'
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->method() === 'POST'
            && $request['username'] === 'sms_username'
            && $request['password'] === 'sms_password'
            && $request['key'] === 'value');
    }

    #[Test]
    public function it_sets_and_returns_the_successful_response_status_correctly(): void
    {
        Http::fake([
            'https://sms.asanak.ir/webservice/v2rest/end-point' => Http::response(['meta' => [
                'status' => 200,
                'message' => 'success',
            ]]), // status `200` is successful
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_sets_and_returns_the_failed_response_status_correctly(): void
    {
        Http::fake([
            'https://sms.asanak.ir/webservice/v2rest/end-point' => Http::response(['meta' => [
                'status' => 1008,
                'message' => 'Bad Request, Validation Data Error',
            ]]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('خطای اعتبار سنجی پارامتر های ورودی', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame(1008, $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://sms.asanak.ir/webservice/v2rest/end-point' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', ['end-point', ['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['meta' => ['status' => 200, 'message' => 'success']])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://sms.asanak.ir/webservice/v2rest/sendsms'
            && $request['source'] === '4567'
            && $request['message'] === 'Text message'
            && $request['destination'] === '0913,0914'
            && $request['send_to_blacklist'] === 1
        );
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['meta' => ['status' => 200, 'message' => 'success']])]);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['key_1' => 'value_1', 'key_2' => 'value_2'], '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://sms.asanak.ir/webservice/v2rest/template'
            && $request['template_id'] === 'pattern_code'
            && $request['destination'] === '0913'
            && $request['parameters'] === ['key_1' => 'value_1', 'key_2' => 'value_2']
            && $request['send_to_blacklist'] === 1
        );
    }

    #[Test]
    public function it_throws_exception_if_we_pass_multiple_phone_numbers_to_send_with_pattern(): void
    {
        $this->expectException(UnsupportedMultiplePhonesException::class);
        $this->expectExceptionMessage('Provider "asanak" only supports sending to one phone number at a time for "pattern" message.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', [], '4567']);
    }

    #[Test]
    public function it_throws_exception_if_we_pass_non_key_value_pairs_to_send_with_pattern(): void
    {
        $this->expectException(InvalidPatternStructureException::class);
        $this->expectExceptionMessage('Provider "asanak" only accepts pattern data as key-value pairs.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['value_1', 'value_2', 'value_2'], '4567']);
    }

    #[Test]
    public function it_throws_an_exception_for_sending_otp(): void
    {
        $this->expectException(UnsupportedMethodException::class);
        $this->expectExceptionMessage('Provider "asanak" does not support sending "otp" message, please use "pattern" method instead.');

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['013', 'Otp message', '4567']);
    }

    #[Test]
    public function it_returns_credit_successfully(): void
    {
        Http::fake(['*' => Http::response([
            'meta' => ['status' => 200, 'message' => 'success'],
            'data' => ['credit' => 1000],
        ])]);

        $credit = $this->driver()->credit();

        $this->assertSame(1000, $credit);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://sms.asanak.ir/webservice/v2rest/getrialcredit'
            && $request['username'] === 'sms_username'
            && $request['password'] === 'sms_password'
            && $request->method() === 'POST'
        );
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): AsanakDriver
    {
        Config::set('iran-sms.providers.asanak.username', 'sms_username');
        Config::set('iran-sms.providers.asanak.password', 'sms_password');
        Config::set('iran-sms.providers.asanak.from', '123');

        return $this->app->make(AsanakDriver::class);
    }
}
