<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Mikrotik;
use App\Contracts\FileStore;
use App\Contracts\ProcessRunner;
use Illuminate\Support\Facades\Log;

/**
 * Manages MikroTik configuration backups.
 */
class MikrotikBackupService
{
    public function __construct(
        private readonly FileStore $fileStore,
        private readonly ProcessRunner $processRunner,
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications
    ) {
    }

    /**
     * Create backup for a MikroTik.
     */
    public function backup(Mikrotik $mikrotik): ?array
    {
        $backupDir = config('vpnpanel.paths.backups') . '/mikrotik/' . $mikrotik->site_name;
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupFile = "{$backupDir}/backup_{$timestamp}.rsc";

        // In sandbox/local mode, we simulate the backup
        // In production, this would use SSH/SCP to fetch from MikroTik
        $content = $this->generateMockBackup($mikrotik);

        try {
            $this->fileStore->put($backupFile, $content);

            $checksum = md5($content);
            $fileSize = strlen($content);

            $backup = $mikrotik->routerBackups()->create([
                'backup_path' => $backupFile,
                'checksum' => $checksum,
                'file_size' => $fileSize,
            ]);

            $this->auditLog->log('mikrotik_backup_created', $mikrotik, [
                'path' => $backupFile,
                'size' => $fileSize,
            ]);

            Log::info("Backup created for {$mikrotik->site_name}: {$backupFile}");

            return [
                'status' => 'success',
                'path' => $backupFile,
                'checksum' => $checksum,
                'size' => $fileSize,
            ];
        } catch (\Exception $e) {
            $this->notifications->backupFailed('mikrotik', $e->getMessage());
            Log::error("Backup failed for {$mikrotik->site_name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * List backups for a MikroTik.
     */
    public function listBackups(Mikrotik $mikrotik): array
    {
        $backupDir = config('vpnpanel.paths.backups') . '/mikrotik/' . $mikrotik->site_name;
        return $this->fileStore->listFiles($backupDir);
    }

    /**
     * Delete old backups (retention policy).
     */
    public function cleanup(int $retentionDays = 30): int
    {
        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        $oldBackups = \App\Models\RouterBackup::where('created_at', '<', $cutoff)->get();

        foreach ($oldBackups as $backup) {
            try {
                $this->fileStore->delete($backup->backup_path);
                $backup->delete();
                $deleted++;
            } catch (\Exception $e) {
                Log::warning("Failed to delete backup {$backup->backup_path}: " . $e->getMessage());
            }
        }

        $this->auditLog->log('backups_cleaned', null, [
            'deleted' => $deleted,
            'retention_days' => $retentionDays,
        ]);

        Log::info("Cleaned up {$deleted} old backups");

        return $deleted;
    }

    /**
     * Generate mock backup content (for development/testing).
     */
    private function generateMockBackup(Mikrotik $mikrotik): string
    {
        $content = "# MikroTik RouterOS Backup\n";
        $content .= "# Site: {$mikrotik->site_name}\n";
        $content .= "# Generated: " . now()->toDateTimeString() . "\n";
        $content .= "# VPN IP: {$mikrotik->vpn_ip}\n";
        $content .= "# RouterOS Version: {$mikrotik->routeros_version}\n";
        $content .= "\n";
        $content .= "# Mock configuration export\n";
        $content .= "/system identity print\n";
        $content .= "/ip address print\n";
        $content .= "/interface print\n";

        return $content;
    }
}
