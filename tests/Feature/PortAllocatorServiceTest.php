<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\NginxMapping;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortAllocatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private \App\Services\PortAllocatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('vpnpanel.ports.winbox_base', 8291);
        config()->set('vpnpanel.ports.winbox_max', 8299);
        config()->set('vpnpanel.ports.webfig_base', 8080);
        config()->set('vpnpanel.ports.webfig_max', 8089);
        $this->service = app(\App\Services\PortAllocatorService::class);
    }

    public function test_sequential_allocation(): void
    {
        $this->assertSame(8291, $this->service->allocateWinboxPort());
        $this->assertSame(8080, $this->service->allocateWebfigPort());

        // Create mapping to occupy port
        $mikrotik = \App\Models\Mikrotik::factory()->create();
        NginxMapping::create(['mikrotik_id' => $mikrotik->id, 'conn_type' => 'winbox', 'public_port' => 8291, 'is_enabled' => true]);

        $this->assertSame(8292, $this->service->allocateWinboxPort());
    }

    public function test_specific_port_conflict(): void
    {
        $mikrotik = \App\Models\Mikrotik::factory()->create();
        NginxMapping::create(['mikrotik_id' => $mikrotik->id, 'conn_type' => 'winbox', 'public_port' => 8300, 'is_enabled' => true]);

        $this->expectException(\RuntimeException::class);
        $this->service->allocateSpecificPort(8300, 'winbox');
    }

    public function test_exhaustion_throws(): void
    {
        config()->set('vpnpanel.ports.winbox_base', 8291);
        config()->set('vpnpanel.ports.winbox_max', 8292);

        $mikrotik = \App\Models\Mikrotik::factory()->create();
        NginxMapping::create(['mikrotik_id' => $mikrotik->id, 'conn_type' => 'winbox', 'public_port' => 8291, 'is_enabled' => true]);
        NginxMapping::create(['mikrotik_id' => $mikrotik->id, 'conn_type' => 'winbox', 'public_port' => 8292, 'is_enabled' => true]);

        $this->expectException(\RuntimeException::class);
        $this->service->allocateWinboxPort();
    }

    public function test_nominal_stats(): void
    {
        $stats = $this->service->getStats();
        $this->assertSame(0, $stats['winbox']['used']);
        $this->assertSame(9, $stats['winbox']['total']); // 8291..8299
    }
}