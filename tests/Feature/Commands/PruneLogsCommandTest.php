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
