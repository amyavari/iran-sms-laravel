<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests;

use AliYavari\IranSms\IranSmsServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionMethod;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * Invoke a protected or private method on the given object using reflection.
     */
    protected function callProtectedMethod(object $object, string $method, array $args = []): mixed
    {
        $reflectionMethod = new ReflectionMethod($object, $method);

        return $reflectionMethod->invoke($object, ...$args);
    }

    /**
     * {@inheritdoc}
     */
    protected function defineEnvironment($app)
    {
        config()->set('database.default', 'testing');

        $migrations = File::allFiles(__DIR__.'/../database/migrations');

        foreach ($migrations as $migration) {
            (include $migration->getRealPath())->up();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getPackageProviders($app)
    {
        return [
            IranSmsServiceProvider::class,
        ];
    }
}
