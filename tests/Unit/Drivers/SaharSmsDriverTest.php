<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\SaharSmsDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class SaharSmsDriverTest extends TestCase
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
        Http::fake(['*' => Http::response(['messageid' => 12345, 'message' => 'Success'])]);

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['09121234567', 'Test OTP', '123']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://www.saharsms.com/api/sahar_token/json/SendVerify'
            && $request->method() === 'POST'
            && $request['receptor'] === '+989121234567'
            && $request['token'] === 'Test OTP'
            && $request['template'] === 'saharsms_otp');
    }

    #[Test]
    public function it_sets_and_returns_the_successful_response_status_correctly(): void
    {
        Http::fake(['*' => Http::response(['messageid' => 12345, 'message' => 'Success'])]);

        $driver = $this->driver();
        $this->callProtectedMethod($driver, 'sendOtp', ['09121234567', 'Test OTP', '123']);

        $this->assertTrue($this->callProtectedMethod($driver, 'isSuccessful'));
    }

    #[Test]
    public function it_sets_and_returns_the_failed_response_status_correctly(): void
    {
        Http::fake(['*' => Http::response(['return' => ['status' => 400, 'message' => 'پارامترها ناقص هستند']])]);

        $driver = $this->driver();
        $this->callProtectedMethod($driver, 'sendOtp', ['09121234567', 'Test OTP', '123']);

        $this->assertFalse($this->callProtectedMethod($driver, 'isSuccessful'));
        $this->assertSame('پارامترها ناقص هستند', $this->callProtectedMethod($driver, 'getErrorMessage'));
        $this->assertSame(400, $this->callProtectedMethod($driver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake(['*' => function () {
            throw new ConnectionException('Connection failed');
        }]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['09121234567', 'Test OTP', '123']);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['messageid' => 12345, 'message' => 'Success'])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['09121234567'], 'Text message', '123']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://www.saharsms.com/api/sahar_token/json/SendVerify'
            && $request['receptor'] === '+989121234567'
            && $request['token'] === 'Text message'
            && $request['template'] === 'saharsms_otp');
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['messageid' => 12345, 'message' => 'Success'])]);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['09121234567'], 'my_template', ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'], '123']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://www.saharsms.com/api/sahar_token/json/sendPatternSMS'
            && $request['receptor'] === '+989121234567'
            && $request['name'] === 'my_template'
            && $request['token1'] === 'value1'
            && $request['token2'] === 'value2'
            && $request['token3'] === 'value3');
    }

    #[Test]
    public function it_throws_exception_if_we_pass_multiple_phone_numbers_to_send_with_pattern(): void
    {
        $this->expectException(UnsupportedMultiplePhonesException::class);
        $this->expectExceptionMessage('Provider "sahar_sms" only supports sending to one phone number at a time for "pattern" message.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['09121234567', '09129876543'], 'template', ['value1'], '123']);
    }

    #[Test]
    public function it_throws_exception_if_we_pass_multiple_phone_numbers_to_send_with_text(): void
    {
        $this->expectException(UnsupportedMultiplePhonesException::class);
        $this->expectExceptionMessage('Provider "sahar_sms" only supports sending to one phone number at a time for "text" message.');

        $this->callProtectedMethod($this->driver(), 'sendText', [['09121234567', '09129876543'], 'Text message', '123']);
    }

    #[Test]
    public function it_throws_exception_if_pattern_has_too_many_tokens(): void
    {
        $this->expectException(InvalidPatternStructureException::class);
        $this->expectExceptionMessage('Provider "sahar_sms" supports maximum 5 tokens in pattern messages.');

        $variables = ['key1' => 'token1', 'key2' => 'token2', 'key3' => 'token3', 'key4' => 'token4', 'key5' => 'token5', 'key6' => 'token6'];
        $this->callProtectedMethod($this->driver(), 'sendPattern', [['09121234567'], 'template', $variables, '123']);
    }

    #[Test]
    public function it_sends_otp_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['messageid' => 12345, 'message' => 'Success'])]);

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['09121234567', 'Your code: 123456', '123']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://www.saharsms.com/api/sahar_token/json/SendVerify'
            && $request['receptor'] === '+989121234567'
            && $request['token'] === 'Your code: 123456'
            && $request['template'] === 'saharsms_otp');
    }

    #[Test]
    public function it_formats_phone_numbers_correctly(): void
    {
        Http::fake(['*' => Http::response(['messageid' => 12345])]);

        // Test Iranian mobile number starting with 0
        $this->callProtectedMethod($this->driver(), 'sendOtp', ['09121234567', 'Test', '123']);
        Http::assertSent(fn (Request $request) => $request['receptor'] === '+989121234567');

        Http::fake(['*' => Http::response(['messageid' => 12345])]);

        // Test Iranian mobile number starting with 98
        $this->callProtectedMethod($this->driver(), 'sendOtp', ['989121234567', 'Test', '123']);
        Http::assertSent(fn (Request $request) => $request['receptor'] === '+989121234567');

        Http::fake(['*' => Http::response(['messageid' => 12345])]);

        // Test 10-digit number (assume Iranian)
        $this->callProtectedMethod($this->driver(), 'sendOtp', ['9121234567', 'Test', '123']);
        Http::assertSent(fn (Request $request) => $request['receptor'] === '+989121234567');
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): SaharSmsDriver
    {
        Config::set('iran-sms.providers.sahar_sms.token', 'sahar_token');
        Config::set('iran-sms.providers.sahar_sms.from', '123');

        return $this->app->make(SaharSmsDriver::class);
    }
}
