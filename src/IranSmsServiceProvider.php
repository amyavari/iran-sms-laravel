<?php

declare(strict_types=1);

namespace AliYavari\IranSms;

use AliYavari\IranSms\Commands\PruneLogsCommand;
use AliYavari\IranSms\Contracts\Sms;
use AliYavari\IranSms\Drivers\AmootSmsDriver;
use AliYavari\IranSms\Drivers\AsanakDriver;
use AliYavari\IranSms\Drivers\BehinPayamDriver;
use AliYavari\IranSms\Drivers\FaraPayamakDriver;
use AliYavari\IranSms\Drivers\FarazSmsDriver;
use AliYavari\IranSms\Drivers\GhasedakDriver;
use AliYavari\IranSms\Drivers\KavenegarDriver;
use AliYavari\IranSms\Drivers\MedianaDriver;
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
    /**
     * All supported providers, with the driver they resolve to.
     *
     * @var array<string, class-string<Abstracts\Driver>>
     */
    private const array DRIVERS = [
        'sms_ir' => SmsIrDriver::class,
        'meli_payamak' => MeliPayamakDriver::class,
        'payam_resan' => PayamResanDriver::class,
        'kavenegar' => KavenegarDriver::class,
        'faraz_sms' => FarazSmsDriver::class,
        'raygan_sms' => RayganSmsDriver::class,
        'web_one' => WebOneDriver::class,
        'amoot_sms' => AmootSmsDriver::class,
        'fara_payamak' => FaraPayamakDriver::class,
        'ghasedak' => GhasedakDriver::class,
        'behin_payam' => BehinPayamDriver::class,
        'asanak' => AsanakDriver::class,
        'mediana' => MedianaDriver::class,
    ];

    public function configurePackage(Package $package): void
    {
        $package->name('iran-sms')
            ->hasConfigFile()
            ->hasMigration('create_sms_logs_table')
            ->hasCommand(PruneLogsCommand::class)
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToStarRepoOnGitHub('amyavari/iran-sms-laravel');
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SmsManager::class, function (Application $app): SmsManager {
            $manager = new SmsManager($app);

            foreach (self::DRIVERS as $driver => $class) {
                $manager->extend($driver, fn (): Sms => $app->make($class));
            }

            return $manager;
        });

        foreach (self::DRIVERS as $driver => $class) {
            $this->app->bind($class, fn (): Sms => new $class(...config()->array("iran-sms.providers.{$driver}")));
        }
    }
}
