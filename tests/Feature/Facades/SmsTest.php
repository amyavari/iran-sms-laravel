<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Feature\Facades;

use AliYavari\IranSms\Drivers\FakeDriver;
use AliYavari\IranSms\Dtos\MockResponse;
use AliYavari\IranSms\Facades\Sms;
use AliYavari\IranSms\SmsManager;
use AliYavari\IranSms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use UnexpectedValueException;

final class SmsTest extends TestCase
{
    #[Test]
    public function it_returns_instance_of_sms_manager_class(): void
    {
        $this->assertInstanceOf(SmsManager::class, Sms::getFacadeRoot());
    }

    #[Test]
    public function it_returns_mocked_response_with_successful_condition(): void
    {
        $mockedResponse = Sms::successfulRequest();

        $this->assertEquals(MockResponse::successful(), $mockedResponse);
    }

    #[Test]
    public function it_returns_mocked_response_with_failed_condition(): void
    {
        $mockedResponse = Sms::failedRequest('Custom error Message');

        $this->assertEquals(MockResponse::failed('Custom error Message'), $mockedResponse);
    }

    #[Test]
    public function it_returns_mocked_response_with_throw_exception_condition(): void
    {
        $mockedResponse = Sms::failedConnection();

        $this->assertEquals(MockResponse::throw(), $mockedResponse);
    }

    #[Test]
    public function it_fakes_default_sms_provider_as_successful_without_specifying_anything(): void
    {
        Sms::fake();

        $sms = Sms::otp('012', 'test')->send();

        $this->assertInstanceOf(FakeDriver::class, $sms);
        $this->assertTrue($sms->successful());
    }

    #[Test]
    public function it_fakes_sms_providers_as_successful_if_we_do_not_pass_any_configs(): void
    {
        $sampleDrivers = ['default', 'test_driver', 'test'];

        Sms::fake($sampleDrivers);

        collect($sampleDrivers)
            ->map(fn (string $driver) => $driver === 'default' ? null : $driver)
            ->each(function (?string $driver) {
                $sms = Sms::driver($driver)->otp('012', 'test')->send();

                $this->assertInstanceOf(FakeDriver::class, $sms);
                $this->assertTrue($sms->successful());
                $this->assertNull($sms->error());
            });
    }

    #[Test]
    public function it_fakes_all_sms_providers_as_successful_when_user_sets_global_config(): void
    {
        $sampleDrivers = ['default', 'test_driver', 'test'];

        Sms::fake($sampleDrivers, Sms::successfulRequest());

        collect($sampleDrivers)
            ->map(fn (string $driver) => $driver === 'default' ? null : $driver)
            ->each(function (?string $driver) {
                $sms = Sms::driver($driver)->otp('012', 'test')->send();

                $this->assertInstanceOf(FakeDriver::class, $sms);
                $this->assertTrue($sms->successful());
                $this->assertNull($sms->error());
            });
    }

    #[Test]
    public function it_fakes_all_sms_providers_as_failed_when_user_sets_global_config(): void
    {
        $sampleDrivers = ['default', 'test_driver', 'test'];

        Sms::fake($sampleDrivers, Sms::failedRequest('Custom error message'));

        collect($sampleDrivers)
            ->map(fn (string $driver) => $driver === 'default' ? null : $driver)
            ->each(function (?string $driver) {
                $sms = Sms::driver($driver)->otp('012', 'test')->send();

                $this->assertInstanceOf(FakeDriver::class, $sms);
                $this->assertFalse($sms->successful());
                $this->assertSame('Custom error message', $sms->error());
            });
    }

    #[Test]
    public function it_fakes_all_sms_providers_as_failed_connection_when_user_sets_global_config_assert_default(): void
    {
        $sampleDrivers = ['default', 'test_driver', 'test'];

        Sms::fake($sampleDrivers, Sms::failedConnection());

        $this->expectException(ConnectionException::class);

        Sms::otp('012', 'test')->send();
    }

    #[Test]
    public function it_fakes_all_sms_providers_as_failed_connection_when_user_sets_global_config_assert_second_key(): void
    {
        $sampleDrivers = ['default', 'test_driver', 'test'];

        Sms::fake($sampleDrivers, Sms::failedConnection());

        $this->expectException(ConnectionException::class);

        Sms::driver('test_driver')->otp('012', 'test')->send();
    }

    #[Test]
    public function it_fakes_each_provider_with_user_specific_configuration(): void
    {
        Sms::fake([
            'default' => Sms::failedRequest('Custom error message'),
            'test_driver' => Sms::successfulRequest(),
            'test' => Sms::failedConnection(),
        ]);

        $default = Sms::otp('012', 'test')->send();
        $this->assertFalse($default->successful());

        $testDriver = Sms::driver('test_driver')->otp('012', 'test')->send();
        $this->assertTrue($testDriver->successful());

        $this->expectException(ConnectionException::class);
        Sms::driver('test')->otp('012', 'test')->send();
    }

    #[Test]
    public function it_throws_an_exception_if_we_set_per_provider_config_and_global_config(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid fake setup: you cannot provide both a global mock response and per-provider responses at the same time.');

        Sms::fake([
            'test' => Sms::successfulRequest(),
        ], Sms::successfulRequest());
    }

    #[Test]
    public function it_throws_an_exception_if_we_providers_with_invalid_config(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Collection should only include [AliYavari\IranSms\Dtos\MockResponse] items, but 'array' found at position 0.");

        Sms::fake([
            'test' => [Sms::successfulRequest()],
        ]);
    }

    #[Test]
    public function it_throws_an_exception_if_we_pass_not_string_values_as_providers_in_the_list(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Collection should only include [string] items, but 'bool' found at position 0.");

        Sms::fake([
            true,
            '123', // valid
        ]);
    }
}
