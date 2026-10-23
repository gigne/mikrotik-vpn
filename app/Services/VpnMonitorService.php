<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ProcessRunner;
use App\Models\Mikrotik;
use App\Models\VpnUser;
use App\Support\CommandBuilder;
use Illuminate\Support\Facades\Log;

/**
 * Monitors VPN server and MikroTik connectivity.
 */
class VpnMonitorService
{
    public function __construct(
        private readonly ProcessRunner $processRunner,
        private readonly CommandBuilder $commandBuilder,
        private readonly NotificationService $notifications
    ) {
    }

        /**
         * Check VPN server status (xl2tpd, ipsec services).
         *
         * Result is cached for 60 seconds so the dashboard never blocks on
         * systemctl. The scheduler (vpnpanel:monitor) refreshes it every minute.
         */
        public function checkVpnServerStatus(): array
        {
            return \Illuminate\Support\Facades\Cache::remember('vpnpanel.server_status', 60, function (): array {
                return $this->runServerStatusCheck();
            });
        }

        private function runServerStatusCheck(): array
        {
            $services = config('vpnpanel.services');
            $status = [];

            foreach (['xl2tpd', 'ipsec'] as $serviceKey) {
                $serviceName = $services[$serviceKey];
                $result = $this->processRunner->run(
                    $this->commandBuilder->build(['systemctl', 'is-active', $serviceName]),
                    5
                );

            $status[$serviceKey] = [
                'service' => $serviceName,
                'status' => $result->successful() ? 'active' : 'inactive',
                'exit_code' => $result->exitCode,
            ];
        }

        // Check if any critical service is down
        $allActive = collect($status)->every(fn($s) => $s['status'] === 'active');

        if (! $allActive) {
            $downServices = collect($status)
                ->filter(fn($s) => $s['status'] !== 'active')
                ->keys()
                ->implode(', ');

            $this->notifications->vpnServerDown("Services down: {$downServices}");
        }

        return [
            'overall' => $allActive ? 'healthy' : 'degraded',
            'services' => $status,
            'checked_at' => now(),
        ];
    }

    /**
     * Check connectivity to all MikroTik devices.
     */
    public function checkAllMikrotiks(): array
    {
        $mikrotiks = Mikrotik::all();
        $results = [];

        foreach ($mikrotiks as $mikrotik) {
            $results[] = $this->checkMikrotik($mikrotik);
        }

        return $results;
    }

    /**
     * Check single MikroTik connectivity.
     *
     * Uses ppp interface detection instead of ping because ICMP doesn't work
     * through IPsec transport mode (only UDP/1701 is encrypted). When a
     * MikroTik connects via L2TP/IPsec, a ppp interface is created with its
     * vpn_ip as the peer address.
     */
    public function checkMikrotik(Mikrotik $mikrotik): array
    {
        $isOnline = false;
        $latency = null;

        // Check if ppp interface exists with this vpn_ip as peer
        $result = $this->processRunner->run(
            ['ip', 'addr', 'show'],
            5
        );

        if ($result->successful()) {
            // Look for "peer <vpn_ip>" in ppp interface output
            $isOnline = (bool) preg_match('/peer\s+' . preg_quote($mikrotik->vpn_ip, '/') . '/', $result->stdout);
        }

        // Update status in database
        $wasOnline = $mikrotik->is_online;
        $mikrotik->update([
                    'is_online' => $isOnline,
            'last_seen_at' => $isOnline ? now() : $mikrotik->last_seen_at,
        ]);

        // Notify if status changed
        if ($wasOnline && ! $isOnline) {
            $this->notifications->mikrotikOffline($mikrotik->site_name, $mikrotik->vpn_ip);
        }

        return [
            'mikrotik_id' => $mikrotik->id,
            'site_name' => $mikrotik->site_name,
            'vpn_ip' => $mikrotik->vpn_ip,
            'status' => $isOnline ? 'online' : 'offline',
            'latency_ms' => $latency,
            'checked_at' => now(),
        ];
    }

    /**
     * Get VPN connection statistics.
     */
    public function getStats(): array
    {
        $totalMikrotiks = Mikrotik::count();
                $onlineMikrotiks = Mikrotik::where('is_online', true)->count();
        $offlineMikrotiks = $totalMikrotiks - $onlineMikrotiks;

        $totalVpnUsers = VpnUser::count();
        $activeVpnUsers = VpnUser::active()->count();

        return [
            'mikrotiks' => [
                'total' => $totalMikrotiks,
                'online' => $onlineMikrotiks,
                'offline' => $offlineMikrotiks,
                'online_percent' => $totalMikrotiks > 0
                    ? round(($onlineMikrotiks / $totalMikrotiks) * 100, 2)
                    : 0,
            ],
            'vpn_users' => [
                'total' => $totalVpnUsers,
                'active' => $activeVpnUsers,
            ],
        ];
    }

    /**
     * Get current active VPN connections (from system).
     */
    public function getActiveConnections(): array
    {
        // In production, parse xl2tpd status or /var/run/xl2tpd/
        // For now, return mock data
        return [
            'active_connections' => Mikrotik::where('is_online', true)->count(),
            'checked_at' => now(),
        ];
    }
}
