<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\FileStore;
use App\Contracts\ProcessRunner;
use App\Models\Mikrotik;
use App\Models\NginxMapping;
use App\Models\VpnUser;
use App\Support\FakeProcessRunner;
use App\Support\SandboxFileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeployConfigsCommandTest extends TestCase
{
    use RefreshDatabase;

    private SandboxFileStore $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new SandboxFileStore(sys_get_temp_dir() . '/vpnpanel-deploy-' . bin2hex(random_bytes(4)));
        $this->app->instance(FileStore::class, $this->files);
        $this->app->instance(ProcessRunner::class, new FakeProcessRunner());
    }

    public function test_it_deploys_chap_secrets_and_nginx_from_the_database(): void
    {
        VpnUser::factory()->create([
            'username' => 'mt-alpha',
            'password' => 'secret123',
            'assigned_ip' => '192.168.43.2',
        ]);

        $mikrotik = Mikrotik::factory()->create([
            'vpn_username' => 'mt-beta',
            'vpn_ip' => '192.168.43.3',
        ]);
        NginxMapping::create([
            'mikrotik_id' => $mikrotik->id,
            'conn_type' => 'winbox',
            'public_port' => 8295,
            'is_enabled' => true,
        ]);
        NginxMapping::create([
            'mikrotik_id' => $mikrotik->id,
            'conn_type' => 'webfig',
            'public_port' => 8085,
            'is_enabled' => true,
        ]);

        $this->artisan('vpnpanel:deploy-configs')->assertSuccessful();

        $chapSecrets = (string) $this->files->get(config('vpnpanel.paths.chap_secrets'));
        $this->assertStringContainsString('"mt-alpha" l2tpd "secret123" 192.168.43.2', $chapSecrets);
        $this->assertStringContainsString('"mt-beta"', $chapSecrets);

        $stream = (string) $this->files->get(config('vpnpanel.paths.nginx_stream_conf'));
        $this->assertStringContainsString('listen 8295;', $stream);
        $this->assertStringContainsString('proxy_pass ', $stream);
        $this->assertStringNotContainsString('stream {', $stream);

        $http = (string) $this->files->get(config('vpnpanel.paths.nginx_conf'));
        $this->assertStringContainsString('listen 8085;', $http);
    }

    public function test_dry_run_writes_nothing(): void
    {
        VpnUser::factory()->create([
            'username' => 'mt-dry',
            'password' => 'secret123',
            'assigned_ip' => '192.168.43.9',
        ]);

        $this->artisan('vpnpanel:deploy-configs', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertFalse($this->files->exists(config('vpnpanel.paths.chap_secrets')));
        $this->assertFalse($this->files->exists(config('vpnpanel.paths.nginx_conf')));
        $this->assertFalse($this->files->exists(config('vpnpanel.paths.nginx_stream_conf')));
    }

    public function test_chap_secrets_only_leaves_nginx_untouched(): void
    {
        VpnUser::factory()->create([
            'username' => 'mt-only',
            'password' => 'secret123',
            'assigned_ip' => '192.168.43.11',
        ]);

        $this->artisan('vpnpanel:deploy-configs', ['--chap-secrets' => true])->assertSuccessful();

        $this->assertTrue($this->files->exists(config('vpnpanel.paths.chap_secrets')));
        $this->assertFalse($this->files->exists(config('vpnpanel.paths.nginx_conf')));
    }

    public function test_nginx_only_leaves_chap_secrets_untouched(): void
    {
        VpnUser::factory()->create([
            'username' => 'mt-nginx',
            'password' => 'secret123',
            'assigned_ip' => '192.168.43.12',
        ]);

        $this->artisan('vpnpanel:deploy-configs', ['--nginx' => true])->assertSuccessful();

        $this->assertTrue($this->files->exists(config('vpnpanel.paths.nginx_conf')));
        $this->assertFalse($this->files->exists(config('vpnpanel.paths.chap_secrets')));
    }

    public function test_it_reports_failure_when_a_service_restart_fails(): void
    {
        VpnUser::factory()->create([
            'username' => 'mt-fail',
            'password' => 'secret123',
            'assigned_ip' => '192.168.43.13',
        ]);

        $runner = new FakeProcessRunner();
        // CommandBuilder prepends 'sudo -n' in production; the fake runner
        // matches on the full argv including those prefix args.
        $runner->setResponse(
            ['sudo', '-n', 'systemctl', 'restart', config('vpnpanel.services.xl2tpd')],
            new \App\Contracts\ProcessResult(
                command: ['sudo', '-n', 'systemctl', 'restart', config('vpnpanel.services.xl2tpd')],
                exitCode: 1,
                stdout: '',
                stderr: 'unit failed',
            ),
        );
        $this->app->instance(ProcessRunner::class, $runner);

        $this->artisan('vpnpanel:deploy-configs', ['--chap-secrets' => true])->assertFailed();
    }
}
