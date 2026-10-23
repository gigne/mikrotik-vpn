<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\FileStore;

/**
 * Sandbox implementation: roots everything under storage/app/sandbox.
 *
 * This lets developers run the panel without root privileges or touching
 * real system paths while still validating file-generation logic end-to-end.
 */
final class SandboxFileStore implements FileStore
{
    private string $root;

    public function __construct(
        private readonly string $basePath,
        string $sandboxSubDir = 'vpn-panel-sandbox'
    ) {
        $this->root = "$basePath/$sandboxSubDir";
    }

    public function resolve(string $logicalPath): string
    {
        // Strip leading slash then prepend the sandbox root
        $relative = ltrim($logicalPath, '/');
        return $this->root . '/' . $relative;
    }

    public function exists(string $logicalPath): bool
    {
        return file_exists($this->resolve($logicalPath));
    }

    public function get(string $logicalPath): ?string
    {
        if (! $this->exists($logicalPath)) {
            return null;
        }
        return file_get_contents($this->resolve($logicalPath));
    }

    public function put(string $logicalPath, string $contents): void
    {
        $physical = $this->resolve($logicalPath);
        $dir = dirname($physical);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Atomic-ish: write to tmp then rename
        $tmp = $physical . '.tmp.' . uniqid();
        file_put_contents($tmp, $contents);
        rename($tmp, $physical);
    }

    public function delete(string $logicalPath): void
    {
        $path = $this->resolve($logicalPath);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function copy(string $fromLogical, string $toLogical): void
    {
        $from = $this->resolve($fromLogical);
        $to = $this->resolve($toLogical);

        if (! is_dir(dirname($to))) {
            mkdir(dirname($to), 0755, true);
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
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (is_file("$path/$item")) {
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
        $this->rmdirRecursive($path);
    }

    private function rmdirRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $p = "$dir/$file";
            is_dir($p) ? $this->rmdirRecursive($p) : unlink($p);
        }
        rmdir($dir);
    }
}
