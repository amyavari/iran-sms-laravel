<?php

declare(strict_types=1);

namespace AliYavari\IranSms;

use AliYavari\IranSms\Commands\PruneLogsCommand;
use AliYavari\IranSms\Drivers\AmootSmsDriver;
use AliYavari\IranSms\Drivers\BehinPayamDriver;
use AliYavari\IranSms\Drivers\FaraPayamakDriver;
use AliYavari\IranSms\Drivers\FarazSmsDriver;
use AliYavari\IranSms\Drivers\GhasedakDriver;
use AliYavari\IranSms\Drivers\KavenegarDriver;
use AliYavari\IranSms\Drivers\LimoSmsDriver;
use AliYavari\IranSms\Drivers\MeliPayamakDriver;
use AliYavari\IranSms\Drivers\PayamResanDriver;
use AliYavari\IranSms\Drivers\RayganSmsDriver;
use AliYavari\IranSms\Drivers\SmsIrDriver;
use AliYavari\IranSms\Drivers\WebOneDriver;
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

        $this->app->bind(
            PayamResanDriver::class,
            fn () => new PayamResanDriver(...config()->array('iran-sms.providers.payam_resan'))
        );

        $this->app->bind(
            KavenegarDriver::class,
            fn () => new KavenegarDriver(...config()->array('iran-sms.providers.kavenegar'))
        );

        $this->app->bind(
            FarazSmsDriver::class,
            fn () => new FarazSmsDriver(...config()->array('iran-sms.providers.faraz_sms'))
        );

        $this->app->bind(
            RayganSmsDriver::class,
            fn () => new RayganSmsDriver(...config()->array('iran-sms.providers.raygan_sms'))
        );

        $this->app->bind(
            WebOneDriver::class,
            fn () => new WebOneDriver(...config()->array('iran-sms.providers.web_one'))
        );

        $this->app->bind(
            AmootSmsDriver::class,
            fn () => new AmootSmsDriver(...config()->array('iran-sms.providers.amoot_sms'))
        );

        $this->app->bind(
            FaraPayamakDriver::class,
            fn () => new FaraPayamakDriver(...config()->array('iran-sms.providers.fara_payamak'))
        );

        $this->app->bind(
            GhasedakDriver::class,
            fn () => new GhasedakDriver(...config()->array('iran-sms.providers.ghasedak'))
        );

        $this->app->bind(
            LimoSmsDriver::class,
            fn () => new LimoSmsDriver(...config()->array('iran-sms.providers.limo_sms'))
        );

        $this->app->bind(
            BehinPayamDriver::class,
            fn () => new BehinPayamDriver(...config()->array('iran-sms.providers.behin_payam'))
        );
    }
}
