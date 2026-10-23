<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Abstraction over the filesystem the panel writes managed files to
 * (chap-secrets, nginx conf, backups).
 *
 * SandboxFileStore re-roots every logical path under storage/app so the panel
 * can be developed and tested without root privileges; LocalFileStore touches
 * the real paths on the Ubuntu VPN server.
 */
interface FileStore
{
    /** Map a logical absolute path (e.g. /etc/ppp/chap-secrets) to a physical path. */
    public function resolve(string $logicalPath): string;

    public function exists(string $logicalPath): bool;

    /** Returns null when the file does not exist. */
    public function get(string $logicalPath): ?string;

    /** Atomically write contents (temp file + rename), creating parent directories. */
    public function put(string $logicalPath, string $contents): void;

    public function delete(string $logicalPath): void;

    /** Copy a logical file to another logical path, creating parent directories. */
    public function copy(string $fromLogical, string $toLogical): void;

    /**
     * Names of regular files in a logical directory (sorted, non-recursive).
     *
     * @return list<string>
     */
    public function listFiles(string $logicalDirectory): array;

    /** File size in bytes, or null when missing. */
    public function size(string $logicalPath): ?int;

    /** Last modified timestamp, or null when missing. */
    public function lastModified(string $logicalPath): ?int;

    /** Recursively remove a logical directory. */
    public function deleteDirectory(string $logicalDirectory): void;
}
