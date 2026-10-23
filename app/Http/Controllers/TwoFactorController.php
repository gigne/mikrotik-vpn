<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Setup page: shows QR + secret + verification form.
     */
    public function setup(Request $request): View
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactor()) {
            return view('profile.two-factor-status', ['user' => $user]);
        }

        $pair = $this->twoFactor->generate($user);

        // Keep the pending secret in session until verified
        session(['2fa_pending_secret' => $pair['secret']]);

        return view('profile.two-factor-setup', [
            'qrDataUrl' => $pair['qr_data_url'],
            'secret' => $pair['secret'],
        ]);
    }

    /**
     * Verify the TOTP code and activate 2FA.
     */
    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $secret = session('2fa_pending_secret');

        if (! $secret || ! $this->twoFactor->verify($secret, $validated['code'])) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        $user = $request->user();
        $recoveryCodes = collect(range(1, 8))
            ->map(fn () => \Illuminate\Support\Str::random(10) . '-' . \Illuminate\Support\Str::random(10))
            ->all();

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        session()->forget('2fa_pending_secret');
        $this->auditLog->log('two_factor_enabled', $user, []);

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Two-factor authentication enabled.');
    }

    /**
     * Show post-login challenge form.
     */
    public function challenge(): View|RedirectResponse
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    /**
     * Verify challenge (TOTP or recovery code) and resume the session.
     */
    public function verifyChallenge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $code = trim($validated['code']);

        if ($this->twoFactor->verify(decrypt($user->two_factor_secret), $code)) {
            session(['2fa_verified' => true]);

            return redirect()->intended(route('dashboard'));
        }

        // Try recovery code
        if ($this->twoFactor->useRecoveryCode($user, $code)) {
            session(['2fa_verified' => true]);

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['code' => 'Invalid code.']);
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->twoFactor->disable($user);
        session()->forget('2fa_verified');
        $this->auditLog->log('two_factor_disabled', $user, []);

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Two-factor authentication disabled.');
    }
}