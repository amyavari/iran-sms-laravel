<?php

declare(strict_types=1);

use AliYavari\IranSms\Enums\Type;
use AliYavari\IranSms\Models\SmsLog;

beforeEach(function (): void {
    setTestNow('2025-12-10 18:30:10');
});

it('deletes the logs created before the given days and keeps the others', function (): void {
    $oldLog = createSmsLog('2025-12-08 18:30:09'); // One second before the cutoff
    $cutLog = createSmsLog('2025-12-08 18:30:10'); // Exactly 2 days ago
    $newLog = createSmsLog('2025-12-08 18:30:11'); // One second after the cutoff

    $this->artisan('iran-sms:prune-logs --days=2')
        ->expectsOutputToContain('Logs created before [2 days] ago pruned successfully.');

    $this->assertModelMissing($oldLog);
    $this->assertModelExists($cutLog);
    $this->assertModelExists($newLog);
});

it('uses 30 days when no days are given', function (): void {
    $oldLog = createSmsLog('2025-11-10 18:30:09'); // One second before the 30-day cutoff
    $newLog = createSmsLog('2025-11-10 18:30:10'); // Exactly 30 days ago

    $this->artisan('iran-sms:prune-logs')
        ->expectsOutputToContain('Logs created before [30 days] ago pruned successfully.');

    $this->assertModelMissing($oldLog);
    $this->assertModelExists($newLog);
});

it('deletes every log created before now when days is 0', function (): void {
    $oldLog = createSmsLog('2025-12-10 18:30:09'); // One second before now
    $newLog = createSmsLog('2025-12-10 18:30:10'); // Exactly now

    $this->artisan('iran-sms:prune-logs --days=0')
        ->expectsOutputToContain('Logs created before [0 days] ago pruned successfully.')
        ->assertExitCode(0);

    $this->assertModelMissing($oldLog);
    $this->assertModelExists($newLog);
});

it('fails and deletes nothing when days is invalid', function (string $option): void {
    $log = createSmsLog('2025-12-09 18:30:10'); // One day ago

    $this->artisan("iran-sms:prune-logs {$option}")
        ->expectsOutputToContain('The --days option must be a whole number of 0 or more.')
        ->assertExitCode(1);

    $this->assertModelExists($log);
})->with([
    'no value' => '--days',
    'text' => '--days=abc',
    'negative' => '--days=-5',
    'decimal' => '--days=1.5',
]);

// ------------
// Helpers
// ------------

/**
 * Only `created_at` matters for pruning, the other values are fixed.
 */
function createSmsLog(string $createdAt): SmsLog
{
    return SmsLog::query()->forceCreate([
        'type' => Type::Otp,
        'driver' => 'test',
        'from' => '5000555',
        'to' => ['09123456789'],
        'content' => ['message' => 'OTP message'],
        'is_successful' => true,
        'error' => null,
        'created_at' => $createdAt,
    ]);
}
