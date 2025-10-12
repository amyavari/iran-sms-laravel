<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\PayamResanDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use PHPUnit\Framework\Attributes\Test;

final class PayamResanDriverTest extends TestCase
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
            'https://api.sms-webservice.com/api/V3/end-point?*' => Http::response(['id' => 1]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        Http::assertSent(function (Request $request) {
            $uri = Uri::of($request->url());

            return $request->hasHeader('Accept', 'application/json')
                && Str::of($request->url())->startsWith('https://api.sms-webservice.com/api/V3/end-point')
                && $request->method() === 'GET'
                && $uri->query()->get('ApiKey') === 'sms_token'
                && $uri->query()->get('key') === 'value';
        });
    }

    #[Test]
    public function it_sets_and_returns_the_response_status_correctly(): void
    {
        // Driver doesn't return any error response, only ID of SMS.
        Http::fake([
            'https://api.sms-webservice.com/api/V3/end-point?*' => Http::response(['id' => 123]),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('شناسه پیام برای پیگیری "123" می باشد.', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame(123, $this->callProtectedMethod($smsDriver, 'getErrorCode')); // ID of SMS
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://api.sms-webservice.com/api/V3/*' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', ['end-point', ['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['id' => 1])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(function (Request $request) {
            $uri = Uri::of($request->url());

            return Str::of($request->url())->startsWith('https://api.sms-webservice.com/api/V3/Send')
                && $uri->query()->get('Text') === 'Text message'
                && $uri->query()->get('Sender') === '4567'
                && $uri->query()->get('Recipients') === ['0913', '0914'];
        });
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['id' => 1])]);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['p1' => 'value_1', 'p2' => 'value_2', 'p3' => 'value_3'], '4567']);

        Http::assertSent(function (Request $request) {
            $uri = Uri::of($request->url());

            return Str::of($request->url())->startsWith('https://api.sms-webservice.com/api/V3/SendTokenSingle')
                && $uri->query()->get('Destination') === '0913'
                && $uri->query()->get('TemplateKey') === 'pattern_code'
                && $uri->query()->get('p1') === 'value_1'
                && $uri->query()->get('p2') === 'value_2'
                && $uri->query()->get('p3') === 'value_3';
        });
    }

    #[Test]
    public function it_throws_exception_if_we_pass_multiple_phone_numbers_to_send_with_pattern(): void
    {
        $this->expectException(UnsupportedMultiplePhonesException::class);
        $this->expectExceptionMessage('Provider "payam_resan" only supports sending to one phone number at a time for "pattern" message.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', [], '4567']);
    }

    #[Test]
    public function it_throws_exception_if_we_pass_less_than_three_parameter_to_send_with_pattern(): void
    {
        $this->expectException(InvalidPatternStructureException::class);
        $this->expectExceptionMessage('Provider "payam_resan" only accepts pattern data with exactly 3 items.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['p1' => 'v1', 'p2' => 'v2'], '4567']);
    }

    #[Test]
    public function it_throws_exception_if_we_pass_more_than_three_parameter_to_send_with_pattern(): void
    {
        $this->expectException(InvalidPatternStructureException::class);
        $this->expectExceptionMessage('Provider "payam_resan" only accepts pattern data with exactly 3 items.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['p1' => 'v1', 'p2' => 'v2', 'p3' => 'v3', 'p4' => 'v4'], '4567']);
    }

    #[Test]
    public function it_throws_exception_if_we_pass_non_key_value_pairs_to_send_with_pattern(): void
    {
        $this->expectException(InvalidPatternStructureException::class);
        $this->expectExceptionMessage('Provider "payam_resan" only accepts pattern data as key-value pairs.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['value_1', 'value_2', 'value_2'], '4567']);
    }

    #[Test]
    public function it_throws_an_exception_for_sending_otp(): void
    {
        $this->expectException(UnsupportedMethodException::class);
        $this->expectExceptionMessage('Provider "payam_resan" does not support sending "otp" message, please use "pattern" method instead.');

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['013', 'Otp message', '4567']);
    }

    #[Test]
    public function it_returns_credit_successfully(): void
    {
        Http::fake(['*' => Http::response(['Credit' => 1000])]);

        $credit = $this->driver()->credit();

        $this->assertSame(1000, $credit);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.sms-webservice.com/api/V3/AccountInfo'
                && $request->method() === 'POST'
                && $request->hasHeader('Content-Type', 'application/json')
                && $request['ApiKey'] === 'sms_token'
        );
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): PayamResanDriver
    {
        Config::set('iran-sms.providers.payam_resan.token', 'sms_token');
        Config::set('iran-sms.providers.payam_resan.from', '123');

        return $this->app->make(PayamResanDriver::class);
    }
}
