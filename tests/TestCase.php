<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests;

use AliYavari\IranSms\IranSmsServiceProvider;
use DG\BypassFinals;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

abstract class TestCase extends Orchestra
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->enableFinalClassMocking();
        $this->preventStrayRequests();
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
     * Prevents any request that is not faked.
     */
    private function preventStrayRequests(): void
    {
        Http::preventStrayRequests();
    }

    private function enableFinalClassMocking(): void
    {
        BypassFinals::enable(bypassReadOnly: false);
    }
}
