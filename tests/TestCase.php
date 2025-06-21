<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ReflectionMethod;

abstract class TestCase extends BaseTestCase
{
    protected function callProtectedMethod(object $object, string $method, array $args = []): mixed
    {
        $reflectionMethod = new ReflectionMethod($object, $method);

        return $reflectionMethod->invoke($object, ...$args);
    }
}
