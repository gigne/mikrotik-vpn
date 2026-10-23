<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\VpnMonitorService;
use App\Services\IpAllocatorService;
use App\Services\PortAllocatorService;
use App\Models\AuditLog;
use App\Models\Mikrotik;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly VpnMonitorService $monitor,
        private readonly IpAllocatorService $ipAllocator,
        private readonly PortAllocatorService $portAllocator,
    ) {}

    public function __invoke(): View
    {
        $stats = $this->monitor->getStats();
        // Read cached service status instead of running systemctl on every
        // page load — the scheduler refreshes it every minute via
        // vpnpanel:monitor. Falls back gracefully if the cache is cold.
        $serverStatus = $this->getCachedServerStatus();

        return view('dashboard', [
            'stats' => $stats,
            'serverStatus' => $serverStatus,
            'ipStats' => $this->ipAllocator->getStats(),
            'portStats' => $this->portAllocator->getStats(),
            'recentActivity' => AuditLog::with('user')->latest('created_at')->limit(10)->get(),
            'sites' => Mikrotik::orderBy('site_name')->get(['site_name', 'vpn_ip', 'is_online', 'last_seen_at', 'winbox_public_port', 'webfig_public_port']),
        ]);
    }

    /**
     * Return server status from cache (set by vpnpanel:monitor every minute).
     * Never blocks the dashboard on a live systemctl call.
     *
     * @return array{overall: string, services: array<string, array{service: string, status: string, exit_code: int}>, checked_at: \Illuminate\Support\Carbon|string}
     */
    private function getCachedServerStatus(): array
    {
        $cached = \Illuminate\Support\Facades\Cache::get('vpnpanel.server_status');

        if ($cached !== null) {
            return $cached;
        }

        // Cold cache: report unknown rather than blocking on systemctl.
        $services = config('vpnpanel.services');

        return [
            'overall' => 'unknown',
            'services' => [
                'xl2tpd' => ['service' => $services['xl2tpd'], 'status' => 'unknown', 'exit_code' => -1],
                'ipsec' => ['service' => $services['ipsec'], 'status' => 'unknown', 'exit_code' => -1],
            ],
            'checked_at' => 'never',
        ];
    }
}
