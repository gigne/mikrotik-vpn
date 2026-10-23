<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idles out sessions after N minutes of inactivity.
 */
class SessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $timeoutMinutes = (int) config('vpnpanel.security.session_timeout_minutes', 60);
            $lastActivity = session('vpnpanel_last_activity');

            if ($lastActivity !== null && (time() - (int) $lastActivity) > ($timeoutMinutes * 60)) {
                auth()->guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('error', 'Session expired due to inactivity.');
            }

            session(['vpnpanel_last_activity' => time()]);
        }

        return $next($request);
    }
}