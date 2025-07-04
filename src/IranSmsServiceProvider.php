<?php

declare(strict_types=1);

namespace AliYavari\IranSms;

use AliYavari\IranSms\Commands\PruneLogsCommand;
use AliYavari\IranSms\Drivers\MeliPayamakDriver;
use AliYavari\IranSms\Drivers\SmsIrDriver;
use Illuminate\Foundation\Application;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * @internal
 */
final class IranSmsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('iran-sms')
            ->hasConfigFile()
            ->hasMigration('create_sms_logs_table')
            ->hasCommand(PruneLogsCommand::class)
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

        $this->app->bind(
            SmsIrDriver::class,
            fn () => new SmsIrDriver(...config()->array('iran-sms.providers.sms_ir'))
        );

        $this->app->bind(
            MeliPayamakDriver::class,
            fn () => new MeliPayamakDriver(...config()->array('iran-sms.providers.meli_payamak'))
        );
    }
}
