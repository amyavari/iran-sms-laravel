<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Tests\Unit\Commands;

use AliYavari\IranSms\Enums\Type;
use AliYavari\IranSms\Models\SmsLog;
use AliYavari\IranSms\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

final class PruneLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_delete_logs_based_on_created_at_time(): void
    {
        Carbon::setTestNow('2025-07-01 5:00:00');

        $this->createSmsLog('2025-06-30 5:00:00', 'log_1');
        $this->createSmsLog('2025-06-29 4:59:00', 'log_2'); // More than 2 days ago
        $this->createSmsLog('2025-07-03 5:00:00', 'log_3');
        $this->createSmsLog('2025-07-01 5:00:00', 'log_4');

        $this->artisan('iran-sms:prune-logs --days=2')
            ->expectsOutputToContain('Logs created before [2 days] ago pruned successfully.');

        $this->assertDatabaseMissing(SmsLog::class, ['driver' => 'log_2']); // The 'driver' field is used to store the log name.
        $this->assertDatabaseHas(SmsLog::class, ['driver' => 'log_1']);
        $this->assertDatabaseHas(SmsLog::class, ['driver' => 'log_3']);
        $this->assertDatabaseHas(SmsLog::class, ['driver' => 'log_4']);

        Carbon::setTestNow();
    }

    // -----------------
    // Helper Methods
    // -----------------

    private function createSmsLog(string $createdAt, string $name): SmsLog
    {
        $smsLog = new SmsLog([
            'type' => fake()->randomElement(Type::cases()),
            'driver' => $name,
            'from' => '12345',
            'to' => ['123'],
            'content' => ['message' => 'Hi'],
            'is_successful' => fake()->boolean(),
            'error' => fake()->randomElement([null, 'Error Message']),
        ]);

        $smsLog->created_at = $createdAt;
        $smsLog->save();

        return $smsLog;
    }
}
