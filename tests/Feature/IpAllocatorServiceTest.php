<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\ProvisioningException;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpAllocatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private \App\Services\IpAllocatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('vpnpanel.ip_pool.subnet', '192.168.42.0/24');
        config()->set('vpnpanel.ip_pool.server_ip', '192.168.42.1');
        config()->set('vpnpanel.ip_pool.range_start', '192.168.42.2');
        config()->set('vpnpanel.ip_pool.range_end', '192.168.42.199');
        $this->service = app(\App\Services\IpAllocatorService::class);
    }

    public function test_allocate_returns_first_free_ip(): void
    {
        $this->assertSame('192.168.42.2', $this->service->allocate());

        VpnUser::factory()->create(['username' => 'a', 'assigned_ip' => '192.168.42.2']);
        $this->assertSame('192.168.42.3', $this->service->allocate());
    }

    public function test_allocate_requested_ip(): void
    {
        $this->assertSame('192.168.42.50', $this->service->allocate('192.168.42.50'));

        // Duplicate should throw
        VpnUser::factory()->create(['username' => 'a', 'assigned_ip' => '192.168.42.50']);
        $this->expectException(\RuntimeException::class);
        $this->service->allocate('192.168.42.50');
    }

    public function test_out_of_range_ip_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->allocate('10.0.0.5');
    }

    public function test_server_ip_is_still_allocatable_if_in_range(): void
    {
        // Server ip .1 is outside the allocatable range, so it cannot be allocated
        $this->expectException(\RuntimeException::class);
        $this->service->allocate('192.168.42.1');
    }

    public function test_pool_exhaustion(): void
    {
        config()->set('vpnpanel.ip_pool.range_start', '192.168.42.2');
        config()->set('vpnpanel.ip_pool.range_end', '192.168.42.4');

        VpnUser::factory()->create(['username' => 'a', 'assigned_ip' => '192.168.42.2']);
        VpnUser::factory()->create(['username' => 'b', 'assigned_ip' => '192.168.42.3']);
        VpnUser::factory()->create(['username' => 'c', 'assigned_ip' => '192.168.42.4']);

        $this->expectException(\RuntimeException::class);
        $this->service->allocate();
    }
}