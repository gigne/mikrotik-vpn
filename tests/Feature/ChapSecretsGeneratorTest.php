<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NginxMapping;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChapSecretsGeneratorTest extends TestCase
{
    use RefreshDatabase;

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

        $generator = app(\App\Services\ChapSecretsGeneratorService::class);
        $content = $generator->generate();

        $this->assertStringContainsString('"mt-kantor" l2tpd "password123" 192.168.47.2', $content);
        $this->assertStringContainsString('"mt-gudang" l2tpd "password456" 192.168.47.3', $content);
        $this->assertStringNotContainsString('disabled-user', $content);
        $this->assertSame([], $generator->validate($content));
    }

    public function test_mikrotik_entries_are_included_and_deduplicated(): void
    {
        $mikrotik = \App\Models\Mikrotik::factory()->create([
            'site_name' => 'Kantor',
            'vpn_username' => 'mt-kantor',
            'vpn_ip' => '192.168.47.2',
        ]);

        // VPN user with same username exists (auto-created during provisioning)
        VpnUser::factory()->create([
            'username' => 'mt-kantor',
            'password' => 'x',
            'assigned_ip' => '192.168.47.2',
            'is_enabled' => true,
        ]);

        $generator = app(\App\Services\ChapSecretsGeneratorService::class);
        $content = $generator->generate();

        // Entry appears exactly once
        $this->assertSame(
            1,
            substr_count($content, '"mt-kantor" l2tpd'),
        );
    }
}