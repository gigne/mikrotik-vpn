<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\FileStore;
use Illuminate\Console\Command;

/**
 * Daily schedule + on-demand: back up managed config files (chap-secrets,
 * nginx conf) into timestamped directories and clean up old backups.
 */
class BackupManagedFiles extends Command
{
    protected $signature = 'vpnpanel:backup-files
                            {--retention-days=30 : delete backups older than N days}';

    protected $description = 'Backup chap-secrets and both nginx configs with retention cleanup';

    public function handle(FileStore $files): int
    {
        $backupRoot = config('vpnpanel.paths.backups');
        $stamp = now()->format('Y-m-d_H-i-s');
        $kept = 0;

        $paths = [
            config('vpnpanel.paths.chap_secrets') => 'chap-secrets',
            config('vpnpanel.paths.nginx_conf') => 'http',
            config('vpnpanel.paths.nginx_stream_conf') => 'stream',
        ];

        foreach ($paths as $src => $label) {
            if (! $files->exists($src)) {
                $this->warn("Skipping missing file: {$src}");
                continue;
            }

            $target = "{$backupRoot}/managed/{$stamp}/{$label}." . basename($src);
            $files->copy($src, $target);
            $this->info("Backed up {$src} -> {$target}");
            $kept++;
        }

        // Retention cleanup: drop timestamped directories older than N days
        $cutoff = now()->subDays((int) $this->option('retention-days'))->getTimestamp();
        $removed = 0;

        foreach ($files->listFiles("{$backupRoot}/managed") as $entry) {
            $mtime = $files->lastModified("{$backupRoot}/managed/{$entry}");
            if ($mtime !== null && $mtime < $cutoff) {
                $files->deleteDirectory("{$backupRoot}/managed/{$entry}");
                $removed++;
                $this->line("Removed old backup: {$entry}");
            }
        }

        $this->info("Backup complete: {$kept} files copied, {$removed} old sets removed.");

        return self::SUCCESS;
    }
}