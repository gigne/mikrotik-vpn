<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Prune historical audit logs older than N days.
 *
 * Keeps the database compact, which is particularly important when running
 * SQLite so the database file doesn't grow indefinitely.
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'vpnpanel:prune-audit-logs
                            {--days=90 : delete audit logs older than N days}
                            {--drop-legacy-monitor-checks : immediately delete all historical mikrotik_checked logs regardless of age}';

    protected $description = 'Prune old audit log records to keep the database compact';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        if ($this->option('drop-legacy-monitor-checks')) {
            $legacy = AuditLog::where('event', 'mikrotik_checked')->delete();
            $this->info("Deleted {$legacy} legacy 'mikrotik_checked' audit records.");
        }

        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} audit log records older than {$days} days (before {$cutoff->toDateTimeString()}).");

        return self::SUCCESS;
    }
}
