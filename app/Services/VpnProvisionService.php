<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\VpnUserData;
use App\Exceptions\ProvisioningException;
use App\Models\VpnUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Provisions VPN users and deploys chap-secrets.
 *
 * Each operation is wrapped in a DB transaction. The chap-secrets deploy is
 * best-effort (sandbox driver may skip service restart) and failures are logged
 * but never crash the request - the file will be regenerated on next success.
 */
class VpnProvisionService
{
    public function __construct(
        private readonly IpAllocatorService $ipAllocator,
        private readonly ChapSecretsDeployService $chapSecretsDeploy,
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * Create a VPN user, allocate IP, and deploy chap-secrets.
     *
     * @return VpnUser
     * @throws \RuntimeException when validation or allocation fails
     */
    public function create(VpnUserData $data): VpnUser
    {
        return DB::transaction(function () use ($data): VpnUser {
            if (VpnUser::where('username', $data->username)->exists()) {
                    throw new ProvisioningException("VPN user '{$data->username}' already exists");
            }

            $ip = $data->assignedIp
                ? $this->ipAllocator->allocate($data->assignedIp)
                : $this->ipAllocator->allocate();

            $user = VpnUser::create([
                'username' => $data->username,
                'password' => $data->password,
                'assigned_ip' => $ip,
                'notes' => $data->notes,
                'expired_at' => $data->expiredAt,
                'is_enabled' => $data->isEnabled,
            ]);

            $this->deployChapSecrets();

            $this->auditLog->log('vpn_user_created', $user, [
                'username' => $user->username,
                'assigned_ip' => $ip,
            ]);

            Log::info("VPN user created: {$user->username} ({$ip})");

            return $user->fresh();
        });
    }

    /**
     * Update a VPN user and redeploy chap-secrets.
     */
    public function update(VpnUser $vpnUser, VpnUserData $data): VpnUser
    {
        return DB::transaction(function () use ($vpnUser, $data): VpnUser {
            $oldIp = $vpnUser->assigned_ip;

            // If IP is changing, validate the new one
            if ($data->assignedIp && $data->assignedIp !== $oldIp) {
                if (! $this->ipAllocator->isIpInUse($data->assignedIp) ||
                    $this->ipAllocator->isIpInUse($data->assignedIp) && $vpnUser->assigned_ip === $data->assignedIp) {
                    // OK, self-allocation
                } else {
                                    throw new ProvisioningException("IP {$data->assignedIp} already in use");
                }
            }

            $vpnUser->update([
                'username' => $data->username,
                'password' => $data->password ?: $vpnUser->password,
                'assigned_ip' => $data->assignedIp ?? $vpnUser->assigned_ip,
                'notes' => $data->notes,
                'expired_at' => $data->expiredAt,
                'is_enabled' => $data->isEnabled,
            ]);

            $this->deployChapSecrets();

            $this->auditLog->log('vpn_user_updated', $vpnUser, [
                'old_ip' => $oldIp,
                'new_ip' => $vpnUser->fresh()->assigned_ip,
            ]);

            return $vpnUser->fresh();
        });
    }

    /**
     * Delete a VPN user and redeploy chap-secrets.
     *
     * Uses forceDelete so the assigned IP's unique index is immediately
     * freed for reuse.
     */
    public function delete(VpnUser $vpnUser): void
    {
        DB::transaction(function () use ($vpnUser): void {
            $username = $vpnUser->username;
            $ip = $vpnUser->assigned_ip;

            $vpnUser->forceDelete();

            $this->deployChapSecrets();

            $this->auditLog->log('vpn_user_deleted', null, [
                'username' => $username,
                'assigned_ip' => $ip,
            ]);

            Log::info("VPN user deleted: {$username} ({$ip})");
        });
    }

    /**
     * Toggle enabled/disabled and redeploy.
     */
    public function toggle(VpnUser $vpnUser): VpnUser
    {
        $vpnUser->update(['is_enabled' => ! $vpnUser->is_enabled]);

        $this->deployChapSecrets();

        $this->auditLog->log('vpn_user_toggled', $vpnUser, [
            'is_enabled' => $vpnUser->fresh()->is_enabled,
        ]);

        return $vpnUser->fresh();
    }

    /**
     * Reset a user's password and redeploy.
     */
    public function resetPassword(VpnUser $vpnUser, string $newPassword): VpnUser
    {
        $vpnUser->update(['password' => $newPassword]);

        $this->deployChapSecrets();

        $this->auditLog->log('vpn_user_password_reset', $vpnUser, []);

        return $vpnUser->fresh();
    }

    private function deployChapSecrets(): void
    {
        try {
            $this->chapSecretsDeploy->deploy();
        } catch (\Exception $e) {
            Log::warning('Chap-secrets deploy failed: ' . $e->getMessage());
        }
    }
}
