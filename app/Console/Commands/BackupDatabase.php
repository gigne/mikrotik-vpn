<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dumps the database into the backup root.
 *
 * Supports SQLite (atomic VACUUM INTO snapshot + gzip) and MySQL/MariaDB
 * (mysqldump --single-transaction | gzip).
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

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver", $connection);

        $file = match ($driver) {
            'sqlite' => $this->dumpSqlite($dir, $stamp, $connection),
            'mysql', 'mariadb' => $this->dumpMysql($dir, $stamp, $connection),
            default => null,
        };

        if ($file === null) {
            $this->error("Database dump not supported for driver [{$driver}].");
            return self::FAILURE;
        }

        // Retention: drop dumps older than retention-days
        $cutoff = time() - (int) $this->option('retention-days') * 86400;
        foreach (glob("{$dir}/vpnpanel-*.*.gz") ?: [] as $old) {
            if (filemtime($old) < $cutoff) {
                @unlink($old);
                $this->line('Removed old dump: ' . basename($old));
            }
        }

        $this->info("Database dumped to {$file}");

        return self::SUCCESS;
    }

    /**
     * SQLite backup using an atomic VACUUM INTO snapshot, followed by gzip.
     *
     * VACUUM INTO produces a fully checkpointed, self-contained database
     * without blocking readers and while holding the write lock only for the
     * duration of the copy. No temporary WAL/SHM sidecars are left behind.
     */
    private function dumpSqlite(string $dir, string $stamp, string $connection): ?string
    {
        $targetGz = "{$dir}/vpnpanel-{$stamp}.sqlite.gz";
        $tempSnapshot = "{$dir}/.tmp-vpnpanel-{$stamp}.sqlite";
        $dbPath = (string) config("database.connections.{$connection}.database", database_path('database.sqlite'));

        try {
            // SQLite forbids VACUUM while an active transaction is open on that
            // connection. If there is an outer Laravel transaction (such as
            // during RefreshDatabase tests) or the file is on disk, we run the
            // snapshot on a clean, isolated PDO connection to the same file.
            // For in-memory databases (:memory:), we must use the existing PDO
            // handle because each new connection would see an empty DB.
            if ($dbPath !== ':memory:' && ! str_contains($dbPath, 'mode=memory') && file_exists($dbPath)) {
                $isolatedPdo = new \PDO("sqlite:{$dbPath}", null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_TIMEOUT => 5,
                ]);
                $stmt = $isolatedPdo->prepare('VACUUM INTO ?');
                $stmt->execute([$tempSnapshot]);
            } else {
                DB::connection($connection)->statement('VACUUM INTO ?', [$tempSnapshot]);
            }

            if (! file_exists($tempSnapshot)) {
                $this->error('SQLite VACUUM INTO produced no output file.');
                return null;
            }

            $in = fopen($tempSnapshot, 'rb');
            if ($in === false) {
                $this->error('Failed to open SQLite snapshot for reading.');
                @unlink($tempSnapshot);
                return null;
            }

            $out = gzopen($targetGz, 'wb9');
            if ($out === false) {
                $this->error("Failed to open destination [{$targetGz}] for writing.");
                fclose($in);
                @unlink($tempSnapshot);
                return null;
            }

            while (! feof($in)) {
                $chunk = fread($in, 65536);
                if ($chunk === false) {
                    $this->error('Failed reading SQLite snapshot chunk.');
                    fclose($in);
                    gzclose($out);
                    @unlink($tempSnapshot);
                    @unlink($targetGz);
                    return null;
                }
                gzwrite($out, $chunk);
            }

            fclose($in);
            gzclose($out);

            return $targetGz;
        } catch (Throwable $e) {
            $this->error('SQLite backup failed: ' . $e->getMessage());
            return null;
        } finally {
            if (file_exists($tempSnapshot)) {
                @unlink($tempSnapshot);
            }
        }
    }

    private function dumpMysql(string $dir, string $stamp, string $connection): ?string
    {
        $targetGz = "{$dir}/vpnpanel-{$stamp}.sql.gz";

        $cmd = sprintf(
            '%s --single-transaction --quick --routines %s | gzip > %s',
            escapeshellarg(config('vpnpanel.backups.database.mysqldump', 'mysqldump')),
            $this->connectionArgs($connection),
            escapeshellarg($targetGz),
        );

        exec($cmd, $output, $exit);

        if ($exit !== 0) {
            $this->error('mysqldump failed');
            @unlink($targetGz);
            return null;
        }

        return $targetGz;
    }

    private function connectionArgs(string $connection): string
    {
        return sprintf(
            '-h%s -P%s -u%s -p%s %s',
            escapeshellarg((string) config("database.connections.{$connection}.host", '127.0.0.1')),
            escapeshellarg((string) config("database.connections.{$connection}.port", '3306')),
            escapeshellarg((string) config("database.connections.{$connection}.username", 'root')),
            escapeshellarg((string) config("database.connections.{$connection}.password", '')),
            escapeshellarg((string) config("database.connections.{$connection}.database", 'vpnpanel')),
        );
    }
}