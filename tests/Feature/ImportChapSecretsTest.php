<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportChapSecretsTest extends TestCase
{
    use RefreshDatabase;

    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = sys_get_temp_dir() . '/chap-import-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_file($this->source)) {
            unlink($this->source);
        }

        parent::tearDown();
    }

    private function writeSource(string $contents): void
    {
        file_put_contents($this->source, $contents);
    }

    public function test_it_imports_static_and_dynamic_accounts(): void
    {
        $this->writeSource(
            "# Secrets for authentication\n"
            . '"vpnadmin" l2tpd "AdminSecret1" *' . "\n"
            . '"static-user" l2tpd "StaticSecret1" 192.168.42.50' . "\n"
        );

        $this->artisan('vpnpanel:import-chap-secrets', ['--file' => $this->source])
            ->assertSuccessful();

        $dynamic = VpnUser::where('username', 'vpnadmin')->firstOrFail();
        $this->assertNull($dynamic->assigned_ip, 'wildcard must import as a dynamic (null) address');
        $this->assertSame('AdminSecret1', $dynamic->password);
        $this->assertTrue($dynamic->is_enabled);

        $static = VpnUser::where('username', 'static-user')->firstOrFail();
        $this->assertSame('192.168.42.50', $static->assigned_ip);
    }

    public function test_dry_run_imports_nothing(): void
    {
        $this->writeSource('"vpnadmin" l2tpd "AdminSecret1" *' . "\n");

        $this->artisan('vpnpanel:import-chap-secrets', ['--file' => $this->source, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('vpn_users', ['username' => 'vpnadmin']);
    }

    public function test_it_skips_usernames_owned_by_a_mikrotik(): void
    {
        Mikrotik::factory()->create([
            'vpn_username' => 'mt-kantor',
            'vpn_ip' => '192.168.42.2',
        ]);

        $this->writeSource('"mt-kantor" l2tpd "ShouldNotWin" 192.168.42.2' . "\n");

        $this->artisan('vpnpanel:import-chap-secrets', ['--file' => $this->source])
            ->expectsOutputToContain('Nothing to import.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('vpn_users', ['username' => 'mt-kantor']);
    }

    public function test_it_skips_accounts_already_in_the_panel(): void
    {
        VpnUser::factory()->create([
            'username' => 'existing',
            'password' => 'original-password',
            'assigned_ip' => null,
        ]);

        $this->writeSource('"existing" l2tpd "overwritten-password" *' . "\n");

        $this->artisan('vpnpanel:import-chap-secrets', ['--file' => $this->source])
            ->assertSuccessful();

        $this->assertSame(
            'original-password',
            VpnUser::where('username', 'existing')->value('password'),
            'an existing panel account must never be overwritten by an import',
        );
    }

    public function test_it_skips_a_static_ip_that_is_already_allocated(): void
    {
        VpnUser::factory()->create([
            'username' => 'holder',
            'assigned_ip' => '192.168.42.60',
        ]);

        $this->writeSource('"newcomer" l2tpd "SomeSecret1" 192.168.42.60' . "\n");

        $this->artisan('vpnpanel:import-chap-secrets', ['--file' => $this->source])
            ->expectsOutputToContain('Nothing to import.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('vpn_users', ['username' => 'newcomer']);
    }

    public function test_it_ignores_comments_blank_and_malformed_lines(): void
    {
        $this->writeSource(
            "# comment\n"
            . "\n"
            . "garbage without quotes\n"
            . '"good-one" l2tpd "GoodSecret1" *' . "\n"
        );

        $this->artisan('vpnpanel:import-chap-secrets', ['--file' => $this->source])
            ->assertSuccessful();

        $this->assertSame(1, VpnUser::count());
        $this->assertDatabaseHas('vpn_users', ['username' => 'good-one']);
    }

    public function test_it_fails_when_the_file_cannot_be_read(): void
    {
        $this->artisan('vpnpanel:import-chap-secrets', ['--file' => '/does/not/exist'])
            ->assertFailed();
    }

    public function test_imported_account_survives_a_chap_secrets_deploy(): void
    {
        $this->writeSource('"vpnadmin" l2tpd "AdminSecret1" *' . "\n");

        $this->artisan('vpnpanel:import-chap-secrets', ['--file' => $this->source])->assertSuccessful();
        $this->artisan('vpnpanel:deploy-configs', ['--chap-secrets' => true])->assertSuccessful();

        $deployed = (string) app(\App\Contracts\FileStore::class)
            ->get(config('vpnpanel.paths.chap_secrets'));

        $this->assertStringContainsString('"vpnadmin" l2tpd "AdminSecret1" *', $deployed);
    }
}
