<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ProvisioningException;
use App\Models\NginxMapping;
use App\Models\Mikrotik;
use Illuminate\Support\Facades\Log;

/**
 * Allocates and releases public ports for Winbox and WebFig access.
 */
class PortAllocatorService
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {
    }

    /**
     * Allocate the next available Winbox port.
     */
    public function allocateWinboxPort(): int
    {
        return $this->allocatePort(
            config('vpnpanel.ports.winbox_base'),
            config('vpnpanel.ports.winbox_max'),
            'winbox'
        );
    }

    /**
     * Allocate the next available WebFig port.
     */
    public function allocateWebfigPort(): int
    {
        return $this->allocatePort(
            config('vpnpanel.ports.webfig_base'),
            config('vpnpanel.ports.webfig_max'),
            'webfig'
        );
    }

    /**
     * Allocate a specific port if available.
     */
    public function allocateSpecificPort(int $port, string $type): int
    {
        $usedPorts = $this->getUsedPorts($type);

        if (in_array($port, $usedPorts)) {
                    throw new ProvisioningException("Port {$port} ({$type}) is already in use");
        }

        return $port;
    }

    /**
     * Get all used ports for a given type.
     *
     * @return array<int>
     */
    public function getUsedPorts(string $type): array
    {
            return NginxMapping::where('conn_type', $type)
                ->where('is_enabled', true)
            ->pluck('public_port')
            ->toArray();
    }

    /**
     * Release a port by deleting the nginx mapping.
     */
    public function releasePort(int $port, string $type): void
    {
        NginxMapping::where('public_port', $port)
                ->where('conn_type', $type)
            ->delete();

        $this->auditLog->log('port_released', null, [
            'port' => $port,
            'type' => $type,
        ]);

        Log::info("Port released: {$port} ({$type})");
    }

    /**
     * Get port allocation statistics.
     */
    public function getStats(): array
    {
        $winboxUsed = count($this->getUsedPorts('winbox'));
        $webfigUsed = count($this->getUsedPorts('webfig'));

        $winboxTotal = config('vpnpanel.ports.winbox_max') - config('vpnpanel.ports.winbox_base') + 1;
        $webfigTotal = config('vpnpanel.ports.webfig_max') - config('vpnpanel.ports.webfig_base') + 1;

        return [
            'winbox' => [
                'total' => $winboxTotal,
                'used' => $winboxUsed,
                'free' => $winboxTotal - $winboxUsed,
                'usage_percent' => $winboxTotal > 0 ? round(($winboxUsed / $winboxTotal) * 100, 2) : 0,
            ],
            'webfig' => [
                'total' => $webfigTotal,
                'used' => $webfigUsed,
                'free' => $webfigTotal - $webfigUsed,
                'usage_percent' => $webfigTotal > 0 ? round(($webfigUsed / $webfigTotal) * 100, 2) : 0,
            ],
        ];
    }

    private function allocatePort(int $base, int $max, string $type): int
    {
        $usedPorts = $this->getUsedPorts($type);
        $current = $base;

        while ($current <= $max) {
            if (! in_array($current, $usedPorts)) {
                return $current;
            }
            $current++;
        }

        throw new ProvisioningException("No available {$type} ports in range {$base}-{$max}");
    }
}
