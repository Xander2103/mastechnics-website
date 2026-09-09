<?php

namespace App\Console\Commands;

use App\Models\FormSecurityEvent;
use Illuminate\Console\Command;

/**
 * Retention of the form security log (form_security_events). Events are
 * operational evidence, not customer data: after the retention window
 * (FORM_SECURITY_LOG_RETENTION_DAYS, default 90) they are deleted in
 * batches so a large table never locks for long. Scheduled daily in
 * routes/console.php.
 */
class PruneSecurityLog extends Command
{
    protected $signature = 'forms:prune-security-log {--days= : Override the configured retention in days}';

    protected $description = 'Delete form security events older than the retention window (FORM_SECURITY_LOG_RETENTION_DAYS)';

    private const BATCH = 1000;

    public function handle(): int
    {
        $days = $this->option('days');
        $days = $days !== null && $days !== '' ? (int) $days : (int) config('form-protection.security_log.retention_days', 90);

        if ($days < 1) {
            $this->error('Retention must be at least 1 day.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $deleted = 0;

        do {
            $batch = FormSecurityEvent::query()
                ->where('occurred_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(self::BATCH)
                ->pluck('id');

            if ($batch->isEmpty()) {
                break;
            }

            $deleted += FormSecurityEvent::query()->whereIn('id', $batch)->delete();
        } while ($batch->count() === self::BATCH);

        $this->info("Beveiligingslog: {$deleted} event(s) ouder dan {$days} dagen verwijderd (voor " . $cutoff->format('Y-m-d H:i') . ').');

        return self::SUCCESS;
    }
}
