<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorServiceTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TwoFactorService();
    }

    public function test_verify_accepts_valid_code(): void
    {
        $secret = (new Google2FA())->generateSecretKey(32);
        $engine = new Google2FA();
        $code = $engine->getCurrentOtp($secret);

        $this->assertTrue($this->service->verify($secret, $code));
    }

    public function test_verify_rejects_invalid_code(): void
    {
        $secret = (new Google2FA())->generateSecretKey(32);

        $this->assertFalse($this->service->verify($secret, '000000'));
        $this->assertFalse($this->service->verify($secret, ''));
        $this->assertFalse($this->service->verify($secret, '12345'));
    }

    public function test_recovery_codes_generation_and_consume(): void
    {
        $user = User::factory()->create();

        $codes = $this->service->generateRecoveryCodes($user, 8);
        $this->assertCount(8, $codes);

        $this->assertTrue($this->service->useRecoveryCode($user, $codes[0]));
        $this->assertFalse($this->service->useRecoveryCode($user, $codes[0]));
        $this->assertFalse($this->service->useRecoveryCode($user, 'not-a-code'));

        $user->refresh();
        $remaining = json_decode(decrypt($user->two_factor_recovery_codes), true);
        $this->assertCount(7, $remaining);
    }

    public function test_enable_and_challenge_flow(): void
    {
        $user = User::factory()->create();
        $secret = (new Google2FA())->generateSecretKey(32);
        $engine = new Google2FA();
        $code = $engine->getCurrentOtp($secret);

        $codes = $this->service->generateRecoveryCodes($user, 8);

        $this->assertTrue($this->service->enable($user, $secret, $code, $codes));

        $user->refresh();
        $this->assertTrue($user->hasEnabledTwoFactor());
        $this->assertTrue($this->service->challenge($user, $code));
        $this->assertFalse($this->service->challenge($user, '000000'));

        $this->service->disable($user);
        $user->refresh();
        $this->assertFalse($user->hasEnabledTwoFactor());
    }
}