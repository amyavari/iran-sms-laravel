<?php

declare(strict_types=1);

namespace AliYavari\IranSms;

use AliYavari\IranSms\Drivers\FakeDriver;
use Illuminate\Foundation\Application;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class IranSmsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('iran-sms')
            ->hasConfigFile()
            ->hasMigration('create_sms_logs_table')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToStarRepoOnGitHub('amyavari/iran-sms-laravel');
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SmsManager::class, fn (Application $app) => new SmsManager($app));

        // Bind drivers here.
        $this->app->bind(FakeDriver::class, fn () => new FakeDriver());
    }
}
