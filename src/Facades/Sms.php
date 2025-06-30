<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Facades;

use AliYavari\IranSms\Drivers\FakeDriver;
use AliYavari\IranSms\Dtos\MockResponse;
use AliYavari\IranSms\SmsManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * @method static \AliYavari\IranSms\Contracts\Sms driver(string $driver = null) Get SMS instance for sending by specified driver
 * @method static \AliYavari\IranSms\Contracts\Sms provider(string $provider = null) Get SMS instance for sending by specified provider
 * @method static \AliYavari\IranSms\Contracts\Sms otp(string $phone, string $message) Create OTP SMS instance.
 * @method static \AliYavari\IranSms\Contracts\Sms pattern(string|list<string> $phones, string $patternCode, array<mixed> $variables) Create Pattern SMS instance
 * @method static \AliYavari\IranSms\Contracts\Sms text(string|list<string> $phones, string $message) Create regular text SMS instance
 * @method static \AliYavari\IranSms\Contracts\Sms from(string $from) Set the sender number for the SMS
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
    public static function failedRequest(string $errorMessage = 'Error Message', string|int $errorCode = 0): MockResponse
    {
        return MockResponse::failed($errorMessage, $errorCode);
    }

    /**
     * Get a mock configuration for throwing an exception during SMS sending in tests.
     */
    public static function failedConnection(): MockResponse
    {
        return MockResponse::throw();
    }

    /**
     * Fakes SMS sending for testing purposes.
     *
     * @param  array<string, MockResponse>|list<string>  $providers
     */
    public static function fake(array $providers = [], ?MockResponse $response = null): void
    {
        self::validateFakeSetupInputs($providers, $response);

        self::ensureDriverResponseMapping($providers, $response)
            ->pipeThrough([
                fn (Collection $driverResponseMap) => self::resolveDefaultDriverName($driverResponseMap),
                fn (Collection $driverResponseMap) => self::registerFakeDrivers($driverResponseMap),
            ]);
    }

    /**
     * {@inheritdoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return SmsManager::class;
    }

    /**
     * Validates the inputs used to configure SMS faking.
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
     * Ensures drivers are mapped to their MockResponse in format of [driver => MockResponse]
     *
     * @param  array<string, MockResponse>|list<string>  $drivers
     * @return Collection<string, MockResponse>
     */
    private static function ensureDriverResponseMapping(array $drivers, ?MockResponse $response): Collection
    {
        if ($drivers === []) {
            $drivers = ['default'];
        }

        if (Arr::isList($drivers)) {
            $response ??= self::successfulRequest();

            $drivers = Arr::mapWithKeys($drivers, fn (string $value) => [$value => $response]);
        }

        return collect($drivers);
    }

    /**
     * Replace 'default' key with actual default driver name.
     *
     * @param  Collection<string, MockResponse>  $driverResponseMap
     * @return Collection<string, MockResponse>
     */
    private static function resolveDefaultDriverName(Collection $driverResponseMap): Collection
    {
        return $driverResponseMap->mapWithKeys(function (MockResponse $response, string $driver) {
            $driver = $driver === 'default' ? static::getFacadeRoot()->getDefaultDriver() : $driver;

            return [$driver => $response];
        });
    }

    /**
     * Create a fake driver for each driver and register it with the corresponding mocked response.
     *
     * @param  Collection<string, MockResponse>  $driverResponseMap
     */
    private static function registerFakeDrivers(Collection $driverResponseMap): void
    {
        $driverResponseMap->each(function (MockResponse $response, string $driver) {
            $fakeDriver = new FakeDriver($response);

            static::getFacadeRoot()->setDriver($driver, $fakeDriver);
        });
    }
}
