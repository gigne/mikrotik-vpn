<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Wraps Google Authenticator TOTP for two-factor authentication.
 */
class TwoFactorService
{
    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA();
    }

    /**
     * Generate a new secret + QR data for the given user.
     *
     * @return array{secret: string, qr_data_url: string}
     */
    public function generate(User $user): array
    {
        $secret = $this->engine->generateSecretKey(32);

        $qrCodeUrl = $this->engine->getQRCodeUrl(
            company: config('app.name', 'VPN Panel'),
            holder: $user->email,
            secret: $secret,
        );

        return [
            'secret' => $secret,
            'qr_data_url' => $qrCodeUrl,
        ];
    }

    public function verify(string $secret, string $code): bool
    {
        if ($code === '' || strlen($code) !== 6) {
            return false;
        }

        // Allow slight clock drift: Google2FA default window is 1
        try {
            return $this->engine->verifyKey($secret, $code, 2);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Generate recovery codes and store them (encrypted) on the user.
     *
     * @return list<string> plaintext codes shown once
     */
    public function generateRecoveryCodes(User $user, int $count = 8): array
    {
        $codes = collect(range(1, $count))
            ->map(fn () => Str::random(10) . '-' . Str::random(10))
            ->all();

        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($codes)),
        ])->save();

        return $codes;
    }

    /**
     * Consume (invalidate) a recovery code.
     *
     * @return bool whether the code was valid
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        if (empty($user->two_factor_recovery_codes)) {
            return false;
        }

        try {
            $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        } catch (\Throwable) {
            return false;
        }

        if (! is_array($codes) || ! in_array($code, $codes, true)) {
            return false;
        }

        $remaining = array_values(array_filter($codes, fn ($c) => $c !== $code));
        $user->forceFill([
            'two_factor_recovery_codes' => $remaining !== [] ? encrypt(json_encode($remaining)) : null,
        ])->save();

        return true;
    }

    /**
     * Activate 2FA for the user: persist secret + enable.
     */
    public function enable(User $user, string $secret, string $verificationCode, array $recoveryCodes): bool
    {
        if (! $this->verify($secret, $verificationCode)) {
            return false;
        }

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Disable 2FA and clear stored artefacts.
     */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Verify the current TOTP code against the user's stored secret.
     */
    public function challenge(User $user, string $code): bool
    {
        if (empty($user->two_factor_secret)) {
            return false;
        }

        try {
            $secret = decrypt($user->two_factor_secret);
        } catch (\Throwable) {
            return false;
        }

        return $this->verify($secret, $code);
    }
}