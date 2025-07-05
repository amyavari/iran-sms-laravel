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
    public function it_sends_text_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['Code' => 0, 'Message' => 'successful.'])]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'sendText', [['0913', '0914'], 'Text message', '4567']);

        $expectedAuth = 'Basic '.base64_encode('sms_username:sms_password');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://smspanel.trez.ir/api/smsAPI/SendMessage'
            && $request->hasHeader('Authorization', $expectedAuth)
            && $request['PhoneNumber'] === '4567'
            && $request['Message'] === 'Text message'
            && $request['Mobiles'] === ['0913', '0914']
            && Str::isUlid($request['UserGroupID'])
            && Carbon::createFromTimestamp($request['SendDateInTimeStamp'])->lessThan(now())
        );

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_sends_text_message_with_error(): void
    {
        Http::fake(['*' => Http::response(['Code' => 8, 'Message' => 'Something went wrong.'])]); // 0 is successful

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'sendText', [['0913', '0914'], 'Text message', '4567']);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('Something went wrong.', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame(8, $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error_when_sending_text_message(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);
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
    public function it_sends_pattern_message_with_error(): void
    {
        Http::fake(['*' => Http::response(['Code' => 8, 'Message' => 'Something went wrong.'])]); // 0 is successful

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'sendPattern', [['0913', '0914'], 'pattern_code', ['token1' => 'value_1', 'token2' => 'value_2'], '4567']);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('Something went wrong.', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame(8, $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error_when_sending_pattern_message(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', ['token1' => 'value_1', 'token2' => 'value_2'], '4567']);
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

        $this->callProtectedMethod($smsDriver, 'sendOtp', ['013', 'Otp message', '4567']);

        Http::assertSent(fn (Request $request) => Str::of($request->url())->startsWith('https://raygansms.com/SendMessageWithCode.ashx')
            && Str::of($request->url())->contains('Username=sms_username')
            && Str::of($request->url())->contains('Password=sms_password')
            && Str::of($request->url())->contains('Mobile=013')
            && Str::of($request->url())->contains('Message=Otp%20message'));

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
}
