<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\FileStore;

/**
 * Writes to the real filesystem on the Ubuntu VPN server.
 *
 * Every logical absolute path is used as-is. Parent directories are created
 * with 0755. Writes are atomic (temp file + rename).
 */
final class LocalFileStore implements FileStore
{
    public function __construct(private readonly ?string $basePath = null)
    {
    }

    public function resolve(string $logicalPath): string
    {
        // Accept already-physical paths as-is.
        return $logicalPath;
    }

    public function exists(string $logicalPath): bool
    {
        return file_exists($this->resolve($logicalPath));
    }

    public function get(string $logicalPath): ?string
    {
        $path = $this->resolve($logicalPath);
        if (! is_file($path)) {
            return null;
        }
        $contents = @file_get_contents($path);
        return $contents === false ? null : $contents;
    }

    public function put(string $logicalPath, string $contents): void
    {
        $path = $this->resolve($logicalPath);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $contents);
        rename($tmp, $path);
    }

    public function delete(string $logicalPath): void
    {
        $path = $this->resolve($logicalPath);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public function copy(string $fromLogical, string $toLogical): void
    {
        $from = $this->resolve($fromLogical);
        $to = $this->resolve($toLogical);
        $dir = dirname($to);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($from, $to);
    }

    public function listFiles(string $logicalDirectory): array
    {
        $path = $this->resolve($logicalDirectory);
        if (! is_dir($path)) {
            return [];
        }
        $files = [];
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_file($path . DIRECTORY_SEPARATOR . $item)) {
                $files[] = $item;
            }
        }
        sort($files);
        return $files;
    }

    public function size(string $logicalPath): ?int
    {
        $path = $this->resolve($logicalPath);
        if (! is_file($path)) {
            return null;
        }
        $size = @filesize($path);
        return $size === false ? null : $size;
    }

    public function lastModified(string $logicalPath): ?int
    {
        $path = $this->resolve($logicalPath);
        if (! is_file($path)) {
            return null;
        }
        $time = @filemtime($path);
        return $time === false ? null : $time;
    }

    public function deleteDirectory(string $logicalDirectory): void
    {
        $path = $this->resolve($logicalDirectory);
        if (is_dir($path)) {
            $this->rmdirRecursive($path);
        }
    }

    private function rmdirRecursive(string $dir): void
    {
        $entries = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($entries as $entry) {
            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($full)) {
                $this->rmdirRecursive($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($dir);
    }
}
