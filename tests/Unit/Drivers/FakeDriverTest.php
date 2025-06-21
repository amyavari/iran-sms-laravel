<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Drivers;

use AliYavari\IranSms\Drivers\FakeDriver;
use AliYavari\IranSms\Dtos\MockResponse;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;

final class FakeDriverTest extends TestCase
{
    #[Test]
    public function it_returns_successful_statues(): void
    {
        $driver = $this->driver(MockResponse::successful());

        $this->sendAllSmsTypes($driver); // Should be without any error

        $this->assertTrue($this->callProtectedMethod($driver, 'isSuccessful'));
    }

    #[Test]
    public function it_returns_failed_statues_and_error(): void
    {
        $driver = $this->driver(MockResponse::failed('Our Custom Error'));

        $this->sendAllSmsTypes($driver); // Should be without any error

        $this->assertFalse($this->callProtectedMethod($driver, 'isSuccessful'));
        $this->assertSame('Our Custom Error', $this->callProtectedMethod($driver, 'getErrorMessage'));
    }

    #[Test]
    public function it_throws_connection_exception_for_send_otp(): void
    {
        $driver = $this->driver(MockResponse::throw());

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($driver, 'sendOtp', ['091234567', 'OTP Message', '123']);

    }

    #[Test]
    public function it_throws_connection_exception_for_send_pattern(): void
    {
        $driver = $this->driver(MockResponse::throw());

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($driver, 'sendPattern', [['091234567'], 'pattern_code', ['key' => 'value'], '123']);
    }

    #[Test]
    public function it_throws_connection_exception_for_send_text(): void
    {
        $driver = $this->driver(MockResponse::throw());

        $this->expectException(ConnectionException::class);

        $this->callProtectedMethod($driver, 'sendText', [['091234567'], 'Text Message', '123']);
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function driver(MockResponse $response): FakeDriver
    {
        return new FakeDriver($response);
    }

    private function sendAllSmsTypes(FakeDriver $driver): Collection
    {
        return collect([
            $this->callProtectedMethod($driver, 'sendOtp', ['091234567', 'OTP Message', '123']),
            $this->callProtectedMethod($driver, 'sendText', [['091234567'], 'Text Message', '123']),
            $this->callProtectedMethod($driver, 'sendPattern', [['091234567'], 'pattern_code', ['key' => 'value'], '123']),
        ]);
    }
}
