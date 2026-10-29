<?php

declare(strict_types=1);

use AliYavari\IranSms\Drivers\FakeDriver;
use AliYavari\IranSms\Facades\Sms;
use AliYavari\IranSms\SmsManager;
use AliYavari\IranSms\Tests\Fixtures\TestDriver;

it('returns a new SMS instance for the same provider on each call', function (): void {
    app(SmsManager::class)->extend('test_gateway', fn (): TestDriver => new TestDriver);

    $smsOne = Sms::provider('test_gateway'); // The provider does not change this behaviour
    $smsTwo = Sms::provider('test_gateway');

    expect($smsOne)
        ->not->toBe($smsTwo);
});

it('returns a new fake SMS instance for a faked provider on each call', function (): void {
    Sms::fake(['test_gateway']); // The provider does not change this behaviour

    $smsOne = Sms::provider('test_gateway');
    $smsTwo = Sms::provider('test_gateway');

    expect($smsOne)
        ->toBeInstanceOf(FakeDriver::class)
        ->not->toBe($smsTwo);
});
