<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VpnMonitorService;
use Illuminate\Console\Command;

/**
 * Checks VPN server services and every MikroTik's connectivity.
 *
 * Scheduled to run every minute; state changes (online -> offline) trigger
 * Telegram notifications via the notification service.
 */
class MonitorVpn extends Command
{
    protected $signature = 'vpnpanel:monitor
                            {--mikrotiks-only : skip VPN server service checks}';

    protected $description = 'Monitor VPN server services and MikroTik connectivity';

    public function handle(VpnMonitorService $monitor): int
    {
        if (! $this->option('mikrotiks-only')) {
            $server = $monitor->checkVpnServerStatus();
            $this->line(sprintf(
                'VPN server: %s (xl2tpd=%s, ipsec=%s)',
                $server['overall'],
                $server['services']['xl2tpd']['status'] ?? 'unknown',
                $server['services']['ipsec']['status'] ?? 'unknown',
            ));
        }

        $results = $monitor->checkAllMikrotiks();

        foreach ($results as $result) {
            $this->line(sprintf(
                '  %-20s %-8s %s',
                $result['site_name'],
                $result['status'],
                $result['latency_ms'] !== null ? "({$result['latency_ms']}ms)" : ''
            ));
        }

        return self::SUCCESS;
    }
}