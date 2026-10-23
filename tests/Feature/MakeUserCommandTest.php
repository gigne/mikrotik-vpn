<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_with_a_token(): void
    {
        $this->artisan('vpnpanel:make-user', [
            'email' => 'boss@example.com',
            '--name' => 'Boss',
            '--password' => 'SuperSecret1!',
            '--admin' => true,
            '--token' => true,
        ])->assertSuccessful();

        $user = User::where('email', 'boss@example.com')->firstOrFail();

        $this->assertTrue($user->is_admin);
        $this->assertSame('Boss', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertCount(1, $user->tokens);
        $this->assertSame('panel-cli', $user->tokens->first()->name);
    }

    public function test_it_defaults_to_operator_role(): void
    {
        $this->artisan('vpnpanel:make-user', [
            'email' => 'op@example.com',
            '--password' => 'SuperSecret1!',
        ])->assertSuccessful();

        $user = User::where('email', 'op@example.com')->firstOrFail();

        $this->assertFalse($user->is_admin);
        $this->assertSame('op', $user->name);
    }

    public function test_it_updates_password_without_demoting_an_admin(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'keep@example.com']);

        $this->artisan('vpnpanel:make-user', [
            'email' => 'keep@example.com',
            '--password' => 'BrandNewPass1!',
        ])->assertSuccessful();

        $admin->refresh();

        $this->assertTrue($admin->is_admin, 'admin role must survive a password-only update');
    }

    public function test_it_rejects_conflicting_role_flags(): void
    {
        $this->artisan('vpnpanel:make-user', [
            'email' => 'conflict@example.com',
            '--password' => 'SuperSecret1!',
            '--admin' => true,
            '--operator' => true,
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'conflict@example.com']);
    }

    public function test_it_rejects_a_short_password(): void
    {
        $this->artisan('vpnpanel:make-user', [
            'email' => 'weak@example.com',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }

    public function test_it_rejects_an_invalid_email(): void
    {
        $this->artisan('vpnpanel:make-user', [
            'email' => 'not-an-email',
            '--password' => 'SuperSecret1!',
        ])->assertFailed();
    }

    public function test_the_issued_token_authenticates_against_the_api(): void
    {
        $this->artisan('vpnpanel:make-user', [
            'email' => 'api@example.com',
            '--password' => 'SuperSecret1!',
            '--admin' => true,
            '--token' => true,
        ])->assertSuccessful();

        $user = User::where('email', 'api@example.com')->firstOrFail();
        $token = $user->createToken('probe')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard')
            ->assertOk();
    }
}
