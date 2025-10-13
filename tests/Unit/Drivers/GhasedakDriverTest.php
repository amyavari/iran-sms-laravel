<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\GhasedakDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class GhasedakDriverTest extends TestCase
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
            'https://gateway.ghasedak.me/rest/api/v1/WebService/end-point' => Http::response(['IsSuccess' => true, 'Message' => '', 'StatusCode' => 200]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        Http::assertSent(fn (Request $request) => $request->hasHeader('ApiKey', 'sms_token')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->url() === 'https://gateway.ghasedak.me/rest/api/v1/WebService/end-point'
            && $request->method() === 'POST'
            && $request['key'] === 'value');
    }

    #[Test]
    public function it_sets_and_returns_the_successful_response_status_correctly(): void
    {
        Http::fake([
            'https://gateway.ghasedak.me/rest/api/v1/WebService/end-point' => Http::response([
                'IsSuccess' => true,
                'Message' => 'با موفقیت انجام شد',
                'StatusCode' => 200,
            ]), // IsSuccess `true` is successful
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_sets_and_returns_the_failed_response_status_correctly(): void
    {
        Http::fake([
            'https://gateway.ghasedak.me/rest/api/v1/WebService/end-point' => Http::response([
                'IsSuccess' => false,
                'Message' => 'پارامتر های ورودی صحیح نمی باشد',
                'StatusCode' => 400,
            ]), // All data about error is inside the response
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('پارامتر های ورودی صحیح نمی باشد', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame(400, $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://gateway.ghasedak.me/rest/api/v1/WebService/end-point' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', ['end-point', ['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Carbon::setTestNow('2025-07-16T12:00:00+03:30');

        Http::fake(['*' => Http::response(['IsSuccess' => true, 'Message' => '', 'StatusCode' => 200])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://gateway.ghasedak.me/rest/api/v1/WebService/SendBulkSMS'
            && $request['lineNumber'] === '4567'
            && $request['message'] === 'Text message'
            && $request['receptors'] === ['0913', '0914']
            && $request['clientReferenceId'] === null
            && $request['isVoice'] === false
            && $request['udh'] === false
            && $request['sendDate'] === '2025-07-16T08:30:00.000000Z'
        );
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Carbon::setTestNow('2025-07-16T12:00:00+03:30');

        Http::fake(['*' => Http::response(['IsSuccess' => true, 'Message' => '', 'StatusCode' => 200])]);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', ['key_1' => 'value_1', 'key_2' => 'value_2'], '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://gateway.ghasedak.me/rest/api/v1/WebService/SendOtpSMS'
            && $request['templateName'] === 'pattern_code'
            && $request['receptors'] === [
                ['mobile' => '0913', 'clientReferenceId' => null],
                ['mobile' => '0914', 'clientReferenceId' => null],
            ]
            && $request['inputs'] === [
                ['param' => 'key_1', 'value' => 'value_1'],
                ['param' => 'key_2', 'value' => 'value_2'],
            ]
            && $request['udh'] === false
            && $request['sendDate'] === '2025-07-16T08:30:00.000000Z');
    }

    #[Test]
    public function it_throws_exception_if_we_pass_non_key_value_pairs_to_send_with_pattern(): void
    {
        $this->expectException(InvalidPatternStructureException::class);
        $this->expectExceptionMessage('Provider "ghasedak" only accepts pattern data as key-value pairs.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['value_1', 'value_2'], '4567']);
    }

    #[Test]
    public function it_throws_an_exception_for_sending_otp(): void
    {
        $this->expectException(UnsupportedMethodException::class);
        $this->expectExceptionMessage('Provider "ghasedak" does not support sending "otp" message, please use "pattern" method instead.');

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['013', 'Otp message', '4567']);
    }

    #[Test]
    public function it_returns_credit_successfully(): void
    {
        Http::fake(['*' => Http::response(['Data' => ['Credit' => 1000]])]);

        $credit = $this->driver()->credit();

        $this->assertSame(1000, $credit);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://gateway.ghasedak.me/rest/api/v1/WebService/GetAccountInformation'
                && $request->method() === 'GET'
                && $request->hasHeader('ApiKey', 'sms_token')
        );
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): GhasedakDriver
    {
        Config::set('iran-sms.providers.ghasedak.token', 'sms_token');
        Config::set('iran-sms.providers.ghasedak.from', '123');

        return $this->app->make(GhasedakDriver::class);
    }
}
