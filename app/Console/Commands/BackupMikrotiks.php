<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Mikrotik;
use App\Services\MikrotikBackupService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Daily schedule: backup every MikroTik router configuration.
 */
class BackupMikrotiks extends Command
{
    protected $signature = 'vpnpanel:backup-mikrotiks
                            {--site= : only back up a single site name}';

    protected $description = 'Backup MikroTik router configurations';

    public function handle(
        MikrotikBackupService $backupService,
        NotificationService $notifications,
    ): int {
        $mikrotiks = $this->option('site')
            ? Mikrotik::where('site_name', $this->option('site'))->get()
            : Mikrotik::all();

        $ok = 0;
        $failed = 0;

        foreach ($mikrotiks as $mikrotik) {
            $result = $backupService->backup($mikrotik);
            if ($result === null) {
                $failed++;
                $this->error("Backup failed: {$mikrotik->site_name}");
                $notifications->backupFailed('mikrotik', $mikrotik->site_name);
                continue;
            }
            $ok++;
            $this->info("Backed up {$mikrotik->site_name} -> {$result['path']}");
        }

        $this->info("Backups complete: {$ok} ok, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}