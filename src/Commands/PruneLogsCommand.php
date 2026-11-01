<?php

declare(strict_types=1);

namespace AliYavari\IranSms\Commands;

use AliYavari\IranSms\Models\SmsLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * @internal
 *
 * Command to prune old records from the sms_logs table.
 */
final class PruneLogsCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $signature = 'iran-sms:prune-logs {--days=30 : The number of days to retain SMS logs}';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Prune SMS logs created before the specified number of days';

    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        $days = $this->option('days');

        if (! Str::isMatch('/^\d+$/', (string) $days)) {
            $this->components->error('The --days option must be a whole number of 0 or more.');

            return self::FAILURE;
        }

        $days = (int) $days;

        $this->components->task(
            "Pruning logs created before {$days} days ago ...",
            fn () => SmsLog::query()
                ->where('created_at', '<', now()->subDays($days))
                ->delete()
        );

        $this->components->info("Logs created before [{$days} days] ago pruned successfully.");

        return self::SUCCESS;
    }
}
