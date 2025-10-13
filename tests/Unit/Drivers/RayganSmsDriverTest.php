<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\RayganSmsDriver;
use AliYavari\IranSms\Exceptions\InvalidPatternStructureException;
use AliYavari\IranSms\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use PHPUnit\Framework\Attributes\Test;

final class RayganSmsDriverTest extends TestCase
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
            'https://smspanel.trez.ir/api/end-point' => Http::response(['Code' => 0, 'Message' => 'successful.']),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://smspanel.trez.ir/api/end-point'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', $this->expectedAuth())
            && $request['key'] === 'value');
    }

    #[Test]
    public function it_sets_and_returns_the_successful_response_status_correctly(): void
    {
        Http::fake([
            'https://smspanel.trez.ir/api/end-point' => Http::response(['Code' => 0, 'Message' => 'successful.']), // Code `0` is successful
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_sets_and_returns_the_failed_response_status_correctly(): void
    {
        Http::fake([
            'https://smspanel.trez.ir/api/end-point' => Http::response(['Code' => 8, 'Message' => 'Something went wrong.']),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('Something went wrong.', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame(8, $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://smspanel.trez.ir/api/end-point' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', ['end-point', ['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['Code' => 0, 'Message' => 'successful.'])]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://smspanel.trez.ir/api/smsAPI/SendMessage'
            && $request['PhoneNumber'] === '4567'
            && $request['Message'] === 'Text message'
            && $request['Mobiles'] === ['0913', '0914']
            && Str::isUlid($request['UserGroupID'])
            && Carbon::createFromTimestamp($request['SendDateInTimeStamp'])->lessThan(now())
        );

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['Code' => 0, 'Message' => 'successful.'])]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'sendPattern', [['0913', '0914'], 'pattern_code', ['token1' => 'value_1', 'token2' => 'value_2'], '4567']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://smspanel.trez.ir/api/smsApiWithPattern/SendMessage'
            && $request['AccessHash'] === 'sms_token'
            && $request['PhoneNumber'] === '4567'
            && $request['PatternId'] === 'pattern_code'
            && $request['Mobiles'] === ['0913', '0914']
            && $request['token1'] === 'value_1'
            && $request['token2'] === 'value_2'
            && Str::isUlid($request['UserGroupID'])
            && Carbon::createFromTimestamp($request['SendDateInTimeStamp'])->lessThan(now()));

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_throws_exception_if_we_pass_non_key_value_pairs_to_send_with_pattern(): void
    {
        $this->expectException(InvalidPatternStructureException::class);
        $this->expectExceptionMessage('Provider "raygan_sms" only accepts pattern data as key-value pairs.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['value_1', 'value_2'], '4567']);
    }

    #[Test]
    public function it_sends_otp_message_successfully(): void
    {
        Http::fake(['*' => Http::response(2001)]); // Greater than 2000 is successful

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'sendOtp', ['0913', 'Otp message', '4567']);

        Http::assertSent(function (Request $request) {
            $uri = Uri::of($request->url());

            return Str::of($request->url())->startsWith('https://raygansms.com/SendMessageWithCode.ashx')
                && $request->method() === 'GET'
                && $uri->query()->get('Username') === 'sms_username'
                && $uri->query()->get('Password') === 'sms_password'
                && $uri->query()->get('Mobile') === '0913'
                && $uri->query()->get('Message') === 'Otp message';
        });

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_sends_otp_message_with_error(): void
    {
        Http::fake(['*' => Http::response(8)]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'sendOtp', ['013', 'Otp message', '4567']);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('خطا با کد "8" رخ داده است.', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame(8, $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error_when_sending_otp_message(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['013', 'Otp message', '4567']);
    }

    #[Test]
    public function it_returns_credit_successfully(): void
    {
        Http::fake(['*' => Http::response(['Result' => '1000'])]);

        $credit = $this->driver()->credit();

        $this->assertSame(1000, $credit);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://smspanel.trez.ir/api/smsAPI/GetCredit'
            && $request->hasHeader('Authorization', $this->expectedAuth())
            && $request->method() === 'POST'
        );
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): RayganSmsDriver
    {
        Config::set('iran-sms.providers.raygan_sms.token', 'sms_token');
        Config::set('iran-sms.providers.raygan_sms.username', 'sms_username');
        Config::set('iran-sms.providers.raygan_sms.password', 'sms_password');
        Config::set('iran-sms.providers.raygan_sms.from', '123');

        return $this->app->make(RayganSmsDriver::class);
    }

    private function expectedAuth(): string
    {
        return 'Basic '.base64_encode('sms_username:sms_password');
    }
}
