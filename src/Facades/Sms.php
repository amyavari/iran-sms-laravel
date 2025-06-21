<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Facades;

use AliYavari\IranSms\Drivers\FakeDriver;
use AliYavari\IranSms\Dtos\MockResponse;
use AliYavari\IranSms\SmsManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * @method static \AliYavari\IranSms\Contracts\Sms driver(string $driver = null)
 * @method static \AliYavari\IranSms\Contracts\Sms provider(string $provider = null)
 * @method static \AliYavari\IranSms\Contracts\Sms otp(string $phone, string $message)
 * @method static \AliYavari\IranSms\Contracts\Sms pattern(string|list<string> $phones, string $patternCode, array<string, mixed> $variables)
 * @method static \AliYavari\IranSms\Contracts\Sms text(string|list<string> $phones, string $message)
 * @method static \AliYavari\IranSms\Contracts\Sms from(string $from)
 */
final class Sms extends Facade
{
    /**
     * Get a mock configuration for successful SMS sending in tests.
     */
    public static function successfulRequest(): MockResponse
    {
        return MockResponse::successful();
    }

    /**
     * Get a mock configuration for failed SMS sending in tests.
     */
    public static function failedRequest(string $errorMessage = 'Error Message'): MockResponse
    {
        return MockResponse::failed($errorMessage);
    }

    /**
     * Get a mock configuration for throwing an exception during SMS sending in tests.
     */
    public static function failedConnection(): MockResponse
    {
        return MockResponse::throw();
    }

    /**
     * Fake SMS sending in tests.
     *
     * @param  array<string, MockResponse>|list<string>  $providers
     */
    public static function fake(array $providers = [], ?MockResponse $response = null): void
    {
        self::validateFakeSetupInputs($providers, $response);

        $driverResponse = self::ensureDriverResponseMap($providers, $response);

        collect($driverResponse)
            ->mapWithKeys(function (MockResponse $response, string $driver) {
                $driver = $driver === 'default' ? static::getFacadeRoot()->getDefaultDriver() : $driver;

                return [$driver => $response];
            })
            ->each(function (MockResponse $response, string $driver) {
                $fakeDriver = new FakeDriver($response);

                static::getFacadeRoot()->setDriver($driver, $fakeDriver);
            });
    }

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return SmsManager::class;
    }

    /**
     * Validate the inputs for configuring SMS faking.
     *
     * @param  array<string, mixed>|list<mixed>  $drivers
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    private static function validateFakeSetupInputs(array $drivers, ?MockResponse $response): void
    {
        if (Arr::isAssoc($drivers)) {
            if (! is_null($response)) {
                throw new InvalidArgumentException(
                    'Invalid fake setup: you cannot provide both a global mock response and per-provider responses at the same time.'
                );
            }

            collect($drivers)->ensure(MockResponse::class);

            return;
        }

        collect($drivers)->ensure('string'); /** @phpstan-ignore argument.type */
    }

    /**
     * Ensures drivers are mapped to their MockResponse in format of provider => MockResponse
     *
     * @param  array<string, MockResponse>|list<string>  $drivers
     * @return array<string, MockResponse>
     */
    private static function ensureDriverResponseMap(array $drivers, ?MockResponse $response): array
    {
        if ($drivers === []) {
            $drivers = ['default'];
        }

        if (Arr::isList($drivers)) {
            $response ??= self::successfulRequest();

            return Arr::mapWithKeys($drivers, fn (string $value) => [$value => $response]);
        }

        return $drivers;
    }
}
