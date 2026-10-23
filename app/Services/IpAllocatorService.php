<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ProvisioningException;
use App\Models\VpnUser;
use App\Models\Mikrotik;
use Illuminate\Support\Facades\Log;

/**
 * Allocates and releases VPN IP addresses from the pool.
 */
class IpAllocatorService
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {
    }

    /**
     * Allocate the next available IP from the configured range.
     *
     * @throws \RuntimeException if no IPs available
     */
    public function allocate(?string $requestedIp = null): string
    {
        $subnet = config('vpnpanel.ip_pool.subnet');
        $rangeStart = config('vpnpanel.ip_pool.range_start');
        $rangeEnd = config('vpnpanel.ip_pool.range_end');

        if (! $this->validateSubnet($subnet)) {
                    throw new ProvisioningException("Invalid subnet: {$subnet}");
        }

        // If specific IP requested, validate and allocate
        if ($requestedIp) {
            if (! $this->isInRange($requestedIp, $rangeStart, $rangeEnd)) {
                        throw new ProvisioningException("IP {$requestedIp} is outside allowed range");
            }
            if ($this->isIpInUse($requestedIp)) {
                        throw new ProvisioningException("IP {$requestedIp} is already in use");
            }
            return $requestedIp;
        }

        // Auto-allocate: find first available IP
        $usedIps = $this->getUsedIps();
        $current = ip2long($rangeStart);
        $end = ip2long($rangeEnd);

        while ($current <= $end) {
            $ip = long2ip($current);
            if (! in_array($ip, $usedIps)) {
                return $ip;
            }
            $current++;
        }

        throw new ProvisioningException('No available IPs in the pool');
    }

    /**
     * Check if an IP is already allocated.
     *
     * Includes soft-deleted records so an IP from a recently deleted site is
     * not handed back out before the DB transaction commits the cleanup.
     */
    public function isIpInUse(string $ip): bool
    {
        $vpnUserInUse = VpnUser::withTrashed()->where('assigned_ip', $ip)->exists();
        $mikrotikInUse = Mikrotik::withTrashed()->where('vpn_ip', $ip)->exists();

        return $vpnUserInUse || $mikrotikInUse;
    }

    /**
     * Get all currently used IPs (including soft-deleted records).
     *
     * @return array<string>
     */
    public function getUsedIps(): array
    {
        $vpnIps = VpnUser::withTrashed()->pluck('assigned_ip')->filter()->toArray();
        $mikrotikIps = Mikrotik::withTrashed()->pluck('vpn_ip')->filter()->toArray();

        return array_unique(array_merge($vpnIps, $mikrotikIps));
    }

    /**
     * Release an IP (mark as unused).
     */
    public function release(string $ip): void
    {
        VpnUser::where('assigned_ip', $ip)->update(['assigned_ip' => null]);
        Mikrotik::where('vpn_ip', $ip)->delete();

        $this->auditLog->log('ip_released', null, ['ip' => $ip]);
        Log::info("IP released: {$ip}");
    }

    /**
     * Get pool statistics.
     */
    public function getStats(): array
    {
        $rangeStart = config('vpnpanel.ip_pool.range_start');
        $rangeEnd = config('vpnpanel.ip_pool.range_end');

        $total = ip2long($rangeEnd) - ip2long($rangeStart) + 1;
        $used = count($this->getUsedIps());

        return [
            'total' => $total,
            'used' => $used,
            'free' => $total - $used,
            'usage_percent' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
        ];
    }

    private function validateSubnet(string $subnet): bool
    {
        $parts = explode('/', $subnet);
        if (count($parts) !== 2) {
            return false;
        }

        $ip = $parts[0];
        $mask = (int) $parts[1];

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && $mask >= 0 && $mask <= 32;
    }

    private function isInRange(string $ip, string $start, string $end): bool
    {
        $ipLong = ip2long($ip);
        $startLong = ip2long($start);
        $endLong = ip2long($end);

        return $ipLong >= $startLong && $ipLong <= $endLong;
    }
}
