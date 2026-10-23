<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ProcessRunner;
use App\Models\AuditLog;
use App\Models\Mikrotik;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\VpnMonitorService;
use App\Support\CommandBuilder;
use App\Support\FakeProcessRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseAndAuditMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = sys_get_temp_dir() . '/vpnpanel-test-backup-' . bin2hex(random_bytes(4));
        config(['vpnpanel.paths.backups' => $this->backupDir]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->backupDir)) {
            File::deleteDirectory($this->backupDir);
        }

        parent::tearDown();
    }

    public function test_backup_database_creates_valid_sqlite_gz_snapshot(): void
    {
        // Use a dedicated physical SQLite database for the backup test so that
        // VACUUM INTO runs cleanly outside the test harness transaction.
        $testDb = sys_get_temp_dir() . '/vpnpanel-src-' . bin2hex(random_bytes(4)) . '.sqlite';
        $pdo = new \PDO("sqlite:{$testDb}");
        $pdo->exec('CREATE TABLE test_items (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO test_items (name) VALUES ('vpn-alpha')");

        $originalDefault = config('database.default');
        config([
            'database.default' => 'sqlite_test_backup',
            'database.connections.sqlite_test_backup' => [
                'driver' => 'sqlite',
                'database' => $testDb,
            ],
        ]);

        try {
            $this->artisan('vpnpanel:backup-database')
                ->assertSuccessful();

            $gzFiles = glob("{$this->backupDir}/database/vpnpanel-*.sqlite.gz");
            $this->assertNotEmpty($gzFiles, 'A .sqlite.gz backup file must be created');

            $gz = $gzFiles[0];
            $this->assertGreaterThan(0, filesize($gz));

            // Read and uncompress to verify it is a valid SQLite DB with our data.
            $gzHandle = gzopen($gz, 'rb');
            $this->assertNotFalse($gzHandle);

            $tmpRestored = sys_get_temp_dir() . '/restored-' . bin2hex(random_bytes(4)) . '.sqlite';
            $outHandle = fopen($tmpRestored, 'wb');
            while (! gzeof($gzHandle)) {
                fwrite($outHandle, (string) gzread($gzHandle, 65536));
            }
            gzclose($gzHandle);
            fclose($outHandle);

            $restoredPdo = new \PDO("sqlite:{$tmpRestored}");
            $itemCount = (int) $restoredPdo->query("SELECT count(*) FROM test_items WHERE name = 'vpn-alpha'")->fetchColumn();
            $this->assertSame(1, $itemCount);

            @unlink($tmpRestored);
        } finally {
            config(['database.default' => $originalDefault]);
            @unlink($testDb);
        }
    }

    public function test_vpn_monitor_does_not_create_audit_logs(): void
    {
        $mikrotik = Mikrotik::factory()->create([
            'site_name' => 'HQ',
            'vpn_ip' => '192.168.42.10',
            'is_online' => false,
        ]);

        $monitor = new VpnMonitorService(
            new FakeProcessRunner(),
            new CommandBuilder(),
            $this->app->make(NotificationService::class)
        );

        $beforeCount = AuditLog::count();
        $monitor->checkMikrotik($mikrotik);
        $afterCount = AuditLog::count();

        $this->assertSame($beforeCount, $afterCount, 'Monitoring must not write to audit_logs');
    }

    public function test_prune_audit_logs_command_removes_old_records_and_legacy_checks(): void
    {
        // 1 old record (120 days ago) - insert via query builder so created_at is exact
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'event' => 'user_login',
            'created_at' => now()->subDays(120),
        ]);

        // 1 recent record (10 days ago)
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'event' => 'vpn_user_created',
            'created_at' => now()->subDays(10),
        ]);

        // 1 legacy mikrotik_checked record (recent)
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'event' => 'mikrotik_checked',
            'created_at' => now()->subDays(2),
        ]);

        $this->assertSame(3, AuditLog::count());

        $this->artisan('vpnpanel:prune-audit-logs', [
            '--days' => 90,
            '--drop-legacy-monitor-checks' => true,
        ])->assertSuccessful();

        $remaining = AuditLog::all();
        $this->assertCount(1, $remaining);
        $this->assertSame('vpn_user_created', $remaining->first()->event);
    }
}
