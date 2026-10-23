<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\FileStore;

/**
 * File store that writes the three panel-managed system files
 * (/etc/ppp/chap-secrets, /etc/nginx/stream-proxy.conf,
 * /etc/nginx/conf.d/mikrotik.conf) via a root-privileged atomic-write helper,
 * while delegating everything else (backups, reads, listings) to LocalFileStore.
 *
 * The helper (deploy/vpnpanel-atomic-write) reads contents from stdin and
 * performs an atomic tmp+rename, so secrets never touch argv and there is no
 * window where the file is empty or partially written.
 */
final class SystemFileStore implements FileStore
{
    /** Logical paths the panel owns and must write through sudo. */
    private const MANAGED_PATHS = [
        '/etc/ppp/chap-secrets',
        '/etc/nginx/stream-proxy.conf',
        '/etc/nginx/conf.d/mikrotik.conf',
    ];

    private const HELPER = '/var/www/vpnpanel/deploy/vpnpanel-atomic-write';

    public function __construct(private readonly LocalFileStore $local)
    {
    }

    public function resolve(string $logicalPath): string
    {
        return $this->local->resolve($logicalPath);
    }

    public function exists(string $logicalPath): bool
    {
        return $this->local->exists($logicalPath);
    }

    public function get(string $logicalPath): ?string
    {
        return $this->local->get($logicalPath);
    }

    public function put(string $logicalPath, string $contents): void
    {
        if ($this->isManaged($logicalPath)) {
            $this->writeManaged($logicalPath, $contents);

            return;
        }

        $this->local->put($logicalPath, $contents);
    }

    public function delete(string $logicalPath): void
    {
        $this->local->delete($logicalPath);
    }

    public function copy(string $fromLogical, string $toLogical): void
    {
        $this->local->copy($fromLogical, $toLogical);
    }

    public function listFiles(string $logicalDirectory): array
    {
        return $this->local->listFiles($logicalDirectory);
    }

    public function size(string $logicalPath): ?int
    {
        return $this->local->size($logicalPath);
    }

    public function lastModified(string $logicalPath): ?int
    {
        return $this->local->lastModified($logicalPath);
    }

    public function deleteDirectory(string $logicalDirectory): void
    {
        $this->local->deleteDirectory($logicalDirectory);
    }

    private function isManaged(string $logicalPath): bool
    {
        return in_array($logicalPath, self::MANAGED_PATHS, true);
    }

    /**
     * Write a managed file via the sudo atomic-write helper.
     *
     * Contents are piped to the helper's stdin; the helper (running as root
     * via sudo) writes a temp file beside the target, chowns it to vpnpanel,
     * and atomically renames it into place.
     */
    private function writeManaged(string $logicalPath, string $contents): void
    {
        $owner = 'vpnpanel';
        $mode = $logicalPath === '/etc/ppp/chap-secrets' ? '0600' : '0644';

        // Use proc_open to feed contents via stdin (no shell, no argv secrets).
        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin -> helper
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $command = ['sudo', '-n', self::HELPER, $logicalPath, $owner, $mode];

        $proc = @proc_open($command, $descriptorSpec, $pipes);
        if (! is_resource($proc)) {
            throw new \RuntimeException("Failed to invoke atomic-write helper for {$logicalPath}");
        }

        fwrite($pipes[0], $contents);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);

        if ($exitCode !== 0) {
            $detail = trim($stderr . ($stdout !== '' ? "\n" . $stdout : ''));
            throw new \RuntimeException(
                "Atomic write to {$logicalPath} failed (exit {$exitCode}): {$detail}"
            );
        }
    }
}
