<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\FileStore;
use App\Contracts\ProcessRunner;
use App\Support\FakeProcessRunner;
use App\Support\SandboxFileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end provisioning flow exercising the full service stack against
 * sandbox file storage and a fake process runner.
 */
class ProvisioningFlowTest extends TestCase
{
    use RefreshDatabase;

    private FileStore $files;
    private FakeProcessRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new SandboxFileStore(sys_get_temp_dir() . '/vpnpanel-feature-' . bin2hex(random_bytes(4)));
        $this->runner = new FakeProcessRunner();

        $this->app->instance(FileStore::class, $this->files);
        $this->app->instance(ProcessRunner::class, $this->runner);
    }

    public function test_provision_mikrotik_completes_full_workflow(): void
    {
        $service = app(\App\Services\MikrotikProvisionService::class);

        $mikrotik = $service->provision(\App\DTO\MikrotikData::fromArray([
            'site_name' => 'Cabang Test',
            'description' => 'Integration test branch',
        ]));

        // DB rows
        $this->assertDatabaseHas('mikrotiks', [
            'site_name' => 'Cabang Test',
            'vpn_username' => 'mt-cabang-test',
            'winbox_public_port' => 8291,
            'webfig_public_port' => 8080,
        ]);
        $this->assertDatabaseHas('vpn_users', ['username' => 'mt-cabang-test']);
        $this->assertSame(2, $mikrotik->nginxMappings()->count());

        // chap-secrets written to sandbox
        $chapSecrets = $this->files->get(config('vpnpanel.paths.chap_secrets'));
        $this->assertNotNull($chapSecrets);
        $this->assertStringContainsString('"mt-cabang-test" l2tpd', $chapSecrets);

        // nginx configs written: stream file (winbox) + http file (webfig)
        $nginxConf = $this->files->get(config('vpnpanel.paths.nginx_conf'));
        $this->assertNotNull($nginxConf);
                $this->assertStringContainsString('listen 8080;', $nginxConf);

                $streamConf = $this->files->get(config('vpnpanel.paths.nginx_stream_conf'));
                $this->assertNotNull($streamConf);
                $this->assertStringContainsString('listen 8291;', $streamConf);
                $this->assertStringNotContainsString('stream {', $streamConf);

        // system commands were executed: nginx test/reload + xl2tpd restart
        $this->assertTrue($this->runner->wasCalled('nginx'));
        $this->assertTrue($this->runner->wasCalled('systemctl'));

        // IP allocation continued from 192.168.43.2
        $this->assertSame('192.168.43.2', $mikrotik->vpn_ip);
    }

    public function test_decommission_releases_resources(): void
    {
        $service = app(\App\Services\MikrotikProvisionService::class);

        $mikrotik = $service->provision(\App\DTO\MikrotikData::fromArray([
            'site_name' => 'To Be Removed',
        ]));

        $this->assertDatabaseHas('nginx_mappings', ['mikrotik_id' => $mikrotik->id]);

        $service->decommission($mikrotik);

            $this->assertDatabaseMissing('mikrotiks', ['id' => $mikrotik->id]);
            $this->assertDatabaseMissing('vpn_users', ['username' => 'mt-to-be-removed']);
        $this->assertDatabaseMissing('nginx_mappings', ['mikrotik_id' => $mikrotik->id]);

        // chap-secrets no longer contains the entry
        $this->assertStringNotContainsString(
            'mt-to-be-removed',
            (string) $this->files->get(config('vpnpanel.paths.chap_secrets')),
        );
    }

    public function test_vpn_user_provision_writes_chap_secrets(): void
    {
        $service = app(\App\Services\VpnProvisionService::class);

        $vpnUser = $service->create(\App\DTO\VpnUserData::fromArray([
            'username' => 'branch-01',
            'password' => 'secret123',
        ]));

        $this->assertSame('192.168.43.2', $vpnUser->assigned_ip);

        $this->assertStringContainsString(
            '"branch-01" l2tpd "secret123" 192.168.43.2',
            (string) $this->files->get(config('vpnpanel.paths.chap_secrets')),
        );
    }

    public function test_database_transaction_rolls_back_on_failure(): void
    {
        // Occupy every IP in a tiny range so allocation fails inside provision()
        $this->app['config']->set('vpnpanel.ip_pool.range_start', '192.168.43.200');
        $this->app['config']->set('vpnpanel.ip_pool.range_end', '192.168.43.200');

        \App\Models\VpnUser::factory()->create([
            'username' => 'blocker',
            'assigned_ip' => '192.168.43.200',
        ]);

        $service = app(\App\Services\MikrotikProvisionService::class);

        try {
            $service->provision(\App\DTO\MikrotikData::fromArray([
                'site_name' => 'Should Roll Back',
            ]));
            $this->fail('Expected allocation to fail');
                } catch (\App\Exceptions\ProvisioningException) {
            // expected
        }

        // Everything rolled back
        $this->assertDatabaseMissing('mikrotiks', ['site_name' => 'Should Roll Back']);
        $this->assertDatabaseMissing('vpn_users', ['username' => 'mt-should-roll-back']);
        $this->assertDatabaseMissing('nginx_mappings', ['mikrotik_id' => 1]);
    }
}