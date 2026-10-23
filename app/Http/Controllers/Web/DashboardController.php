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
        $serverStatus = $this->monitor->checkVpnServerStatus();

        return view('dashboard', [
            'stats' => $stats,
            'serverStatus' => $serverStatus,
            'ipStats' => $this->ipAllocator->getStats(),
            'portStats' => $this->portAllocator->getStats(),
            'recentActivity' => AuditLog::with('user')->latest('created_at')->limit(10)->get(),
            'sites' => Mikrotik::orderBy('site_name')->get(['site_name', 'vpn_ip', 'is_online', 'last_seen_at', 'winbox_public_port', 'webfig_public_port']),
        ]);
    }
}
