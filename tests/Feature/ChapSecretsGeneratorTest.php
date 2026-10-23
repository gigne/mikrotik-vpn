<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Mikrotik;
use App\Models\VpnUser;
use App\Services\ChapSecretsGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChapSecretsGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function generator(): ChapSecretsGeneratorService
    {
        return app(ChapSecretsGeneratorService::class);
    }

    public function test_generator_produces_valid_chap_secrets(): void
    {
        VpnUser::factory()->create([
            'username' => 'mt-kantor',
            'password' => 'password123',
            'assigned_ip' => '192.168.47.2',
            'is_enabled' => true,
        ]);

        VpnUser::factory()->create([
            'username' => 'mt-gudang',
            'password' => 'password456',
            'assigned_ip' => '192.168.47.3',
            'is_enabled' => true,
        ]);

        VpnUser::factory()->create([
            'username' => 'disabled-user',
            'password' => 'disabled',
            'assigned_ip' => '192.168.47.90',
            'is_enabled' => false,
        ]);

        $generator = $this->generator();
        $content = $generator->generate();

        $this->assertStringContainsString('"mt-kantor" l2tpd "password123" 192.168.47.2', $content);
        $this->assertStringContainsString('"mt-gudang" l2tpd "password456" 192.168.47.3', $content);
        $this->assertStringNotContainsString('disabled-user', $content);
        $this->assertSame([], $generator->validate($content));
    }

    public function test_expired_users_are_excluded(): void
    {
        VpnUser::factory()->create([
            'username' => 'still-valid',
            'password' => 'pw123456',
            'assigned_ip' => '192.168.47.10',
            'is_enabled' => true,
            'expired_at' => now()->addDay(),
        ]);

        VpnUser::factory()->create([
            'username' => 'long-expired',
            'password' => 'pw123456',
            'assigned_ip' => '192.168.47.11',
            'is_enabled' => true,
            'expired_at' => now()->subDay(),
        ]);

        $content = $this->generator()->generate();

        $this->assertStringContainsString('"still-valid"', $content);
        $this->assertStringNotContainsString('"long-expired"', $content);
    }

    public function test_user_without_static_ip_gets_dynamic_wildcard(): void
    {
        VpnUser::factory()->create([
            'username' => 'roaming-user',
            'password' => 'pw123456',
            'assigned_ip' => null,
            'is_enabled' => true,
        ]);

        $generator = $this->generator();
        $content = $generator->generate();

        $this->assertStringContainsString('"roaming-user" l2tpd "pw123456" *', $content);
        $this->assertSame([], $generator->validate($content));
    }

    public function test_mikrotik_wins_username_collision_with_a_vpn_user(): void
    {
        Mikrotik::factory()->create([
            'site_name' => 'Kantor',
            'vpn_username' => 'mt-kantor',
            'vpn_ip' => '192.168.47.2',
        ]);

        // Provisioning creates a matching VPN user; the MikroTik record must win
        // because it holds the authoritative encrypted password.
        VpnUser::factory()->create([
            'username' => 'mt-kantor',
            'password' => 'should-not-appear',
            'assigned_ip' => '192.168.47.2',
            'is_enabled' => true,
        ]);

        $content = $this->generator()->generate();

        $this->assertSame(1, substr_count($content, '"mt-kantor" l2tpd'));
        $this->assertStringNotContainsString('should-not-appear', $content);
    }

    public function test_entries_are_sorted_and_output_is_deterministic(): void
    {
        foreach (['zeta-site', 'alpha-site', 'mid-site'] as $name) {
            VpnUser::factory()->create([
                'username' => $name,
                'password' => 'pw123456',
                'assigned_ip' => null,
                'is_enabled' => true,
            ]);
        }

        $generator = $this->generator();
        $first = $generator->generate();
        $second = $generator->generate();

        $this->assertSame($first, $second, 'output must be stable for a given database state');

        $body = array_values(array_filter(
            explode("\n", $first),
            static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'),
        ));

        $usernames = array_map(
            static fn (string $line): string => explode('"', $line)[1],
            $body,
        );

        $sorted = $usernames;
        sort($sorted);
        $this->assertSame($sorted, $usernames);
    }

    public function test_quotes_and_backslashes_are_stripped_from_values(): void
    {
        VpnUser::factory()->create([
            'username' => 'odd-user',
            'password' => 'pa"ss\\word',
            'assigned_ip' => '192.168.47.20',
            'is_enabled' => true,
        ]);

        $generator = $this->generator();
        $content = $generator->generate();

        $this->assertStringContainsString('"odd-user" l2tpd "password" 192.168.47.20', $content);
        $this->assertSame([], $generator->validate($content));
    }

    public function test_validate_rejects_malformed_lines(): void
    {
        $generator = $this->generator();

        $this->assertSame([], $generator->validate("# comment only\n\n"));
        $this->assertSame([], $generator->validate('"user" l2tpd "pw" *'));

        $this->assertNotEmpty($generator->validate('not-a-valid-line'));
        $this->assertNotEmpty($generator->validate('"user" l2tpd "pw" 999.1.1.1'));
        $this->assertNotEmpty($generator->validate('"bad user" l2tpd "pw" 192.168.47.2'));
    }

    public function test_validate_reports_the_offending_line_number(): void
    {
        $errors = $this->generator()->validate(
            "# header\n"
            . '"good" l2tpd "pw" 192.168.47.2' . "\n"
            . 'broken line' . "\n",
        );

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Line 3', $errors[0]);
    }
}
