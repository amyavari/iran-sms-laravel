<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests;

use AliYavari\IranSms\IranSmsServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionMethod;

abstract class TestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $migrations = File::allFiles(__DIR__.'/../database/migrations');

        foreach ($migrations as $migration) {
            (include $migration->getRealPath())->up();
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            IranSmsServiceProvider::class,
        ];
    }

    protected function callProtectedMethod(object $object, string $method, array $args = []): mixed
    {
        $reflectionMethod = new ReflectionMethod($object, $method);

        return $reflectionMethod->invoke($object, ...$args);
    }
}
