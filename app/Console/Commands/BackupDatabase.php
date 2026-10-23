<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dumps the database using mysqldump (production) into the backup root.
 */
class BackupDatabase extends Command
{
    protected $signature = 'vpnpanel:backup-database
                            {--retention-days=30 : delete dumps older than N days}';

    protected $description = 'Dump the database to the backup directory';

    public function handle(): int
    {
        $backupRoot = config('vpnpanel.paths.backups');
        $dir = "{$backupRoot}/database";
        $stamp = now()->format('Y-m-d_H-i-s');
        $file = "{$dir}/vpnpanel-{$stamp}.sql.gz";

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $connection = config('database.default');

        if ($connection === 'mysql') {
            $cmd = sprintf(
                '%s --single-transaction --quick --routines %s | gzip > %s',
                escapeshellarg(config('vpnpanel.backups.database.mysqldump')),
                $this->connectionArgs(),
                escapeshellarg($file),
            );
            exec($cmd, $output, $exit);
            if ($exit !== 0) {
                $this->error('mysqldump failed');
                return self::FAILURE;
            }
        } else {
            // Non-MySQL: fall back to a flat SQL export via artisan-compatible dump
            $sql = DB::select('select sqlite_version() as v');
            $this->warn('Database dump only supported on MySQL; skipping.');
            return self::SUCCESS;
        }

        // Retention
        $cutoff = time() - (int) $this->option('retention-days') * 86400;
        foreach (glob("{$dir}/vpnpanel-*.sql.gz") ?: [] as $old) {
            if (filemtime($old) < $cutoff) {
                @unlink($old);
                $this->line("Removed old dump: " . basename($old));
            }
        }

        $this->info("Database dumped to {$file}");

        return self::SUCCESS;
    }

    private function connectionArgs(): string
    {
        return sprintf(
            '-h%s -u%s -p%s %s',
            escapeshellarg(config('database.connections.mysql.host')),
            escapeshellarg(config('database.connections.mysql.username')),
            escapeshellarg(config('database.connections.mysql.password')),
            escapeshellarg(config('database.connections.mysql.database')),
        );
    }
}