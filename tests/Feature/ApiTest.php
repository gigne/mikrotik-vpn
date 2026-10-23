<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\FileStore;
use App\Contracts\ProcessRunner;
use App\Models\User;
use App\Support\FakeProcessRunner;
use App\Support\SandboxFileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(FileStore::class, new SandboxFileStore(sys_get_temp_dir() . '/vpnpanel-api-' . bin2hex(random_bytes(4))));
        $this->app->instance(ProcessRunner::class, new FakeProcessRunner());
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_guests_are_rejected_from_api(): void
    {
        $this->getJson('/api/mikrotik')->assertStatus(401);
        $this->getJson('/api/vpn-users')->assertStatus(401);
        $this->getJson('/api/ip-pools')->assertStatus(401);
    }

    public function test_authenticated_admin_can_list_mikrotiks(): void
    {
        \App\Models\Mikrotik::factory()->count(2)->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/mikrotik')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_mikrotik_api(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/mikrotik', [
                'site_name' => 'API Branch',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('mikrotiks', ['site_name' => 'API Branch']);
    }

    public function test_store_mikrotik_api_validates(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/mikrotik', [
                'site_name' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['site_name']);
    }

    public function test_vpn_user_api_crud(): void
    {
        $token = $this->actingAs($this->admin(), 'sanctum');

        // store
        $response = $this->postJson('/api/vpn-users', [
            'username' => 'api-user',
            'password' => 'password123',
            'assigned_ip' => '192.168.47.60',
        ])->assertStatus(201);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('vpn_users', ['username' => 'api-user']);

        // update
        $this->putJson("/api/vpn-users/{$id}", [
            'username' => 'api-user',
            'password' => '',
            'assigned_ip' => '192.168.47.60',
            'notes' => 'updated via API',
        ])->assertOk();

        $this->assertDatabaseHas('vpn_users', ['notes' => 'updated via API']);

        // destroy (soft delete)
        $this->deleteJson("/api/vpn-users/{$id}")->assertOk();
                $this->assertSoftDeleted('vpn_users', ['id' => $id]);
    }

    public function test_vpn_user_duplicate_ip_rejected(): void
    {
        \App\Models\VpnUser::factory()->create([
            'username' => 'taken',
            'assigned_ip' => '192.168.47.77',
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/vpn-users', [
                'username' => 'new-user',
                'password' => 'password123',
                'assigned_ip' => '192.168.47.77',
            ])
            ->assertStatus(422);
    }

    public function test_system_endpoints(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/reload-nginx')
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/restart-xl2tpd')
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/status')
            ->assertOk();
    }
}