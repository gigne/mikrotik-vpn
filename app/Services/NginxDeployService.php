<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FileStore;
use App\Contracts\ProcessRunner;
use App\Support\CommandBuilder;
use Illuminate\Support\Facades\Log;

/**
 * Deploys nginx configuration (stream file + http file) and reloads nginx.
 *
 * Stream blocks live in a top-level-included file because nginx does not
 * allow `stream {}` inside the http {} context. Both files are backed up,
 * validated, written, then `nginx -t` and reload are run.
 */
class NginxDeployService
{
    public function __construct(
        private readonly FileStore $fileStore,
        private readonly ProcessRunner $processRunner,
        private readonly CommandBuilder $commandBuilder,
        private readonly NginxConfigGeneratorService $generator,
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Generate, validate, backup, and deploy both nginx files.
     *
     * @return array<string, mixed>
     */
    public function deploy(): array
    {
        $streamConfPath = config('vpnpanel.paths.nginx_stream_conf');
        $httpConfPath = config('vpnpanel.paths.nginx_conf');
        $backupPath = config('vpnpanel.paths.backups') . '/nginx';

        $streamContent = $this->generator->generateStreamConfig();
        $httpContent = $this->generator->generateHttpConfig();

        // Validate
        $errors = [...$this->generator->validate($streamContent), ...$this->generator->validate($httpContent)];
        if ($errors !== []) {
            $this->auditLog->log('nginx_validation_failed', null, ['errors' => $errors]);
            throw new \RuntimeException('Nginx config validation failed: ' . implode(', ', $errors));
        }

        $backups = [];
        $timestamp = now()->format('Y-m-d_H-i-s');

        // Deploy both files (backup + write)
        $files = [
            $streamConfPath => ['label' => 'stream', 'content' => $streamContent],
            $httpConfPath => ['label' => 'http', 'content' => $httpContent],
        ];

        foreach ($files as $path => $meta) {
            if ($this->fileStore->exists($path)) {
                $backupFile = "{$backupPath}/{$meta['label']}.{$timestamp}.conf";
                $this->fileStore->copy($path, $backupFile);
                $backups[] = $backupFile;
                Log::info("Nginx ({$meta['label']}) config backed up to: {$backupFile}");
            }

            $this->fileStore->put($path, $meta['content']);
            Log::info("Nginx ({$meta['label']}) config deployed to: {$path}");
        }

        // Test nginx config
        $testResult = $this->testNginx();
        if (! $testResult->successful()) {
            $this->notifications->send(
                'nginx_reload_failed',
                'Nginx Reload Failed',
                "Nginx config test failed: {$testResult->output()}",
                null,
                true,
            );
            throw new \RuntimeException("Nginx config test failed: {$testResult->output()}");
        }

        // Reload nginx
        $reloadResult = $this->reloadNginx();
        if (! $reloadResult->successful()) {
            $this->notifications->send(
                'nginx_reload_failed',
                'Nginx Reload Failed',
                "Failed to reload Nginx: {$reloadResult->output()}",
                null,
                true,
            );
            throw new \RuntimeException("Failed to reload Nginx: {$reloadResult->output()}");
        }

        $this->auditLog->log('nginx_deployed', null, [
            'stream_path' => $streamConfPath,
            'http_path' => $httpConfPath,
            'stream_bytes' => strlen($streamContent),
            'http_bytes' => strlen($httpContent),
        ]);

        return [
            'status' => 'success',
            'stream_path' => $streamConfPath,
            'http_path' => $httpConfPath,
            'backups' => $backups,
        ];
    }

    /**
     * Test nginx configuration syntax.
     */
    public function testNginx()
    {
        return $this->processRunner->run(
            $this->commandBuilder->build(['/usr/sbin/nginx', '-t'])
        );
    }

    /**
     * Reload nginx service.
     */
    public function reloadNginx()
    {
        return $this->processRunner->run(
            $this->commandBuilder->build(['systemctl', 'reload', config('vpnpanel.services.nginx')])
        );
    }

    /**
     * Get current stream config content.
     */
    public function getCurrentStreamContent(): ?string
    {
        return $this->fileStore->get(config('vpnpanel.paths.nginx_stream_conf'));
    }

    /**
     * Get current http config content.
     */
    public function getCurrentHttpContent(): ?string
    {
        return $this->fileStore->get(config('vpnpanel.paths.nginx_conf'));
    }

    /**
     * List nginx config backups.
     */
    public function listBackups(): array
    {
        return $this->fileStore->listFiles(config('vpnpanel.paths.backups') . '/nginx');
    }
}