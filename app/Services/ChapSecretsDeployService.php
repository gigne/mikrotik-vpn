<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FileStore;
use App\Contracts\ProcessRunner;
use App\Support\CommandBuilder;
use Illuminate\Support\Facades\Log;

/**
 * Deploys chap-secrets file to the system and restarts xl2tpd.
 */
class ChapSecretsDeployService
{
    public function __construct(
        private readonly FileStore $fileStore,
        private readonly ProcessRunner $processRunner,
        private readonly CommandBuilder $commandBuilder,
        private readonly ChapSecretsGeneratorService $generator,
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications
    ) {
    }

    /**
     * Generate, validate, backup, and deploy chap-secrets.
     */
    public function deploy(): array
    {
        $chapSecretsPath = config('vpnpanel.paths.chap_secrets');
        $backupPath = config('vpnpanel.paths.backups') . '/chap-secrets';

        // Generate new content
        $content = $this->generator->generate();

        // Validate
        $errors = $this->generator->validate($content);
        if (! empty($errors)) {
            $this->auditLog->log('chap_secrets_validation_failed', null, [
                'errors' => $errors,
            ]);
            throw new \RuntimeException('Chap-secrets validation failed: ' . implode(', ', $errors));
        }

        // Backup existing file
        if ($this->fileStore->exists($chapSecretsPath)) {
            $timestamp = now()->format('Y-m-d_H-i-s');
            $backupFile = "{$backupPath}/chap-secrets.{$timestamp}";
            $this->fileStore->copy($chapSecretsPath, $backupFile);
            Log::info("Chap-secrets backed up to: {$backupFile}");
        }

        // Write new file
        $this->fileStore->put($chapSecretsPath, $content);
        Log::info('Chap-secrets deployed');

        // Restart xl2tpd
        $result = $this->restartXl2tpd();

        if (! $result->successful()) {
            $this->notifications->send(
                'service_restart_failed',
                'Service Restart Failed',
                "Failed to restart xl2tpd: {$result->output()}",
                null,
                true
            );
            throw new \RuntimeException("Failed to restart xl2tpd: {$result->output()}");
        }

        $this->auditLog->log('chap_secrets_deployed', null, [
            'path' => $chapSecretsPath,
            'lines' => substr_count($content, "\n"),
        ]);

        return [
            'status' => 'success',
            'path' => $chapSecretsPath,
            'backup' => $backupFile ?? null,
        ];
    }

    /**
     * Restart xl2tpd service.
     */
    public function restartXl2tpd()
    {
            return $this->processRunner->run(
                $this->commandBuilder->build(['systemctl', 'restart', config('vpnpanel.services.xl2tpd')])
            );
        }

    /**
     * Get current chap-secrets content.
     */
    public function getCurrentContent(): ?string
    {
        $path = config('vpnpanel.paths.chap_secrets');
        return $this->fileStore->get($path);
    }

    /**
     * List chap-secrets backups.
     */
    public function listBackups(): array
    {
        $backupDir = config('vpnpanel.paths.backups') . '/chap-secrets';
        return $this->fileStore->listFiles($backupDir);
    }
}
