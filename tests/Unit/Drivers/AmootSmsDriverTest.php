<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\AmootSmsDriver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;
use AliYavari\IranSms\Exceptions\UnsupportedMultiplePhonesException;
use AliYavari\IranSms\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

final class AmootSmsDriverTest extends TestCase
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
            'https://portal.amootsms.com/rest/end-point?*' => Http::response(['Status' => 'Success']),
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        Http::assertSent(fn (Request $request) => Str::of($request->url())->startsWith('https://portal.amootsms.com/rest/end-point')
            && Str::of($request->url())->contains('Token=sms_token')
            && Str::of($request->url())->contains('key=value'));
    }

    #[Test]
    public function it_sets_and_returns_the_successful_response_status_correctly(): void
    {
        Http::fake([
            'https://portal.amootsms.com/rest/end-point?*' => Http::response(['Status' => 'Success']), // Successful
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertTrue($this->callProtectedMethod($smsDriver, 'isSuccessful'));
    }

    #[Test]
    public function it_sets_and_returns_the_failed_response_status_correctly(): void
    {
        Http::fake([
            'https://portal.amootsms.com/rest/end-point?*' => Http::response(['Status' => 'CreditNotEnough']), // Failed
        ]);

        $smsDriver = $this->driver();

        $this->callProtectedMethod($smsDriver, 'execute', ['end-point', ['key' => 'value']]);

        $this->assertFalse($this->callProtectedMethod($smsDriver, 'isSuccessful'));
        $this->assertSame('CreditNotEnough', $this->callProtectedMethod($smsDriver, 'getErrorMessage'));
        $this->assertSame('', $this->callProtectedMethod($smsDriver, 'getErrorCode'));
    }

    #[Test]
    public function it_throws_exception_for_any_connection_error(): void
    {
        Http::fake([
            'https://portal.amootsms.com/rest/end-point?*' => Http::failedConnection(),
        ]);

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($this->driver(), 'execute', ['end-point', ['key' => 'value']]);
    }

    #[Test]
    public function it_sends_text_message_successfully(): void
    {
        Carbon::setTestNow('2025-07-16T12:00:00+03:30');

        Http::fake(['*' => Http::response(['Status' => 'Success'])]);

        $this->callProtectedMethod($this->driver(), 'sendText', [['0913', '0914'], 'Text message', '4567']);

        Http::assertSent(fn (Request $request) => Str::of($request->url())->startsWith('https://portal.amootsms.com/rest/SendSimple')
            && Str::of($request->url())->contains('SendDateTime=2025-07-16T12%3A00%3A00%2B03%3A30')
            && Str::of($request->url())->contains('SMSMessageText=Text%20message')
            && Str::of($request->url())->contains('LineNumber=4567')
            && Str::of($request->url())->contains('Mobiles=0913%2C0914'));
    }

    #[Test]
    public function it_sends_pattern_message_successfully(): void
    {
        Http::fake(['*' => Http::response(['Status' => 'Success'])]);

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913'], 'pattern_code', ['p1' => 'value_1', 'p2' => 'value_2', 'p3' => 'value_3'], '4567']);

        Http::assertSent(fn (Request $request) => Str::of($request->url())->startsWith('https://portal.amootsms.com/rest/SendWithPattern')
            && Str::of($request->url())->contains('Mobile=0913')
            && Str::of($request->url())->contains('PatternCodeID=pattern_code')
            && Str::of($request->url())->contains('PatternValues=value_1%2Cvalue_2%2Cvalue_3'));
    }

    #[Test]
    public function it_throws_exception_if_we_pass_multiple_phone_numbers_to_send_with_pattern(): void
    {
        $this->expectException(UnsupportedMultiplePhonesException::class);
        $this->expectExceptionMessage('Provider "amoot_sms" only supports sending to one phone number at a time for "pattern" message.');

        $this->callProtectedMethod($this->driver(), 'sendPattern', [['0913', '0914'], 'pattern_code', [], '4567']);
    }

    #[Test]
    public function it_throws_an_exception_for_sending_otp(): void
    {
        $this->expectException(UnsupportedMethodException::class);
        $this->expectExceptionMessage('Provider "amoot_sms" does not support sending "otp" message, please use "pattern" method instead.');

        $this->callProtectedMethod($this->driver(), 'sendOtp', ['013', 'Otp message', '4567']);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(): AmootSmsDriver
    {
        Config::set('iran-sms.providers.amoot_sms.token', 'sms_token');
        Config::set('iran-sms.providers.amoot_sms.from', '123');

        return $this->app->make(AmootSmsDriver::class);
    }
}
