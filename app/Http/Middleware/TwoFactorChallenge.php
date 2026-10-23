<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Challenges users with an enabled-but-unconfirmed 2FA session on every
 * state-changing request.
 */
class TwoFactorChallenge
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->hasEnabledTwoFactor() && ! session('2fa_verified')) {
            if (! $request->is('two-factor*')) {
                return redirect()->route('two-factor.challenge');
            }
        }

        return $next($request);
    }
}