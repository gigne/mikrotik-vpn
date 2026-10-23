<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Mikrotik;
use App\Models\NginxMapping;
use App\Models\VpnUser;
use App\DTO\MikrotikData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates complete MikroTik provisioning workflow.
 * Transactional: all or nothing.
 */
class MikrotikProvisionService
{
    public function __construct(
        private readonly IpAllocatorService $ipAllocator,
        private readonly PortAllocatorService $portAllocator,
        private readonly ChapSecretsDeployService $chapSecretsDeploy,
        private readonly NginxDeployService $nginxDeploy,
        private readonly RouterOSGeneratorService $routerOSGenerator,
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notifications
    ) {
    }

    /**
     * Provision a new MikroTik with all dependencies.
     * Transactional: rolls back on any failure.
         *
         * @param  MikrotikData  $data
         */
        public function provision(MikrotikData $data): Mikrotik
        {
            return DB::transaction(function () use ($data) {
                $siteName = $data->siteName;
                $description = $data->description;

                // 1. Generate VPN credentials
                $vpnUsername = $data->vpnUsername ?? $this->generateVpnUsername($siteName);
                $vpnPassword = $data->vpnPassword ?? $this->generatePassword();

                // 2. Allocate VPN IP
                $vpnIp = $this->ipAllocator->allocate($data->vpnIp);

                // 3. Allocate public ports
                $winboxPublicPort = $data->winboxPublicPort
                            ?? $this->portAllocator->allocateWinboxPort();
                $webfigPublicPort = $data->webfigPublicPort
                            ?? $this->portAllocator->allocateWebfigPort();

                // 4. Create MikroTik record (IP & password stored in vpn_users table only)
                $mikrotik = Mikrotik::create([
                            'site_name' => $siteName,
                            'description' => $description,
                            'vpn_username' => $vpnUsername,
                            'winbox_internal_port' => $data->winboxInternalPort,
                            'webfig_internal_port' => $data->webfigInternalPort,
                            'winbox_public_port' => $winboxPublicPort,
                            'webfig_public_port' => $webfigPublicPort,
                            'routeros_version' => $data->routerosVersion,
                            'ssh_public_key' => $data->sshPublicKey,
                            'is_online' => false,
                ]);

                // 5. Create corresponding VPN user
                VpnUser::create([
                            'username' => $vpnUsername,
                            'password' => $vpnPassword,
                            'assigned_ip' => $vpnIp,
                            'notes' => "Auto-created for MikroTik: {$siteName}",
                            'is_enabled' => true,
                ]);

                // 6. Create nginx mappings
                NginxMapping::create([
                            'mikrotik_id' => $mikrotik->id,
                            'conn_type' => 'winbox',
                            'public_port' => $winboxPublicPort,
                            'is_enabled' => true,
                ]);

                NginxMapping::create([
                            'mikrotik_id' => $mikrotik->id,
                            'conn_type' => 'webfig',
                            'public_port' => $webfigPublicPort,
                            'is_enabled' => true,
                ]);

                // 9. Generate RouterOS script (reads IP/password from VpnUser)
                $script = $this->routerOSGenerator->generate($mikrotik);

                $this->auditLog->log('mikrotik_provisioned', $mikrotik, [
                            'vpn_username' => $vpnUsername,
                            'vpn_ip' => $vpnIp,
                            'winbox_port' => $winboxPublicPort,
                            'webfig_port' => $webfigPublicPort,
                ]);

                Log::info("MikroTik provisioned: {$siteName} ({$vpnIp})");

                return $mikrotik->fresh();
            });
        }

    /**
     * Update MikroTik configuration.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Mikrotik $mikrotik, array $data): Mikrotik
    {
        return DB::transaction(function () use ($mikrotik, $data) {
            // Sync VPN IP change to linked VpnUser
            $vpnUsername = $mikrotik->vpn_username;
            if (isset($data['vpn_ip']) && !empty($data['vpn_ip'])) {
                $oldIp = $mikrotik->vpn_ip;
                if ($data['vpn_ip'] !== $oldIp) {
                    Log::info("Updating VPN IP for {$vpnUsername}: {$oldIp} -> {$data['vpn_ip']}");
                    VpnUser::where('username', $vpnUsername)
                        ->update(['assigned_ip' => $data['vpn_ip']]);
                }
            }

            // Sync VPN password change to linked VpnUser
            if (isset($data['vpn_password']) && $data['vpn_password'] !== null && $data['vpn_password'] !== '') {
                Log::info("Updating VPN password for {$vpnUsername}");
                VpnUser::where('username', $vpnUsername)
                    ->update(['password' => $data['vpn_password']]);
            }

            // Update MikroTik-specific fields only (non-VPN fields)
            $mikrotikData = array_filter([
                'site_name' => $data['site_name'] ?? null,
                'description' => $data['description'] ?? null,
                'winbox_internal_port' => $data['winbox_internal_port'] ?? null,
                'webfig_internal_port' => $data['webfig_internal_port'] ?? null,
                'winbox_public_port' => $data['winbox_public_port'] ?? null,
                'webfig_public_port' => $data['webfig_public_port'] ?? null,
                'routeros_version' => $data['routeros_version'] ?? null,
                'ssh_public_key' => $data['ssh_public_key'] ?? null,
            ], fn($v) => $v !== null);

            if (!empty($mikrotikData)) {
                $mikrotik->update($mikrotikData);
            }

            // If public ports changed, sync the NginxMapping records
            $newWinboxPort = $data['winbox_public_port'] ?? null;
            $newWebfigPort = $data['webfig_public_port'] ?? null;

            if ($newWinboxPort !== null) {
                $mikrotik->nginxMappings()
                    ->where('conn_type', 'winbox')
                    ->update(['public_port' => $newWinboxPort]);
            }

            if ($newWebfigPort !== null) {
                $mikrotik->nginxMappings()
                    ->where('conn_type', 'webfig')
                    ->update(['public_port' => $newWebfigPort]);
            }

            // Always deploy changes after update
            try {
                $this->chapSecretsDeploy->deploy();
                $result = $this->nginxDeploy->deploy();
                Log::info('Nginx deploy result: ' . json_encode($result));
            } catch (\Exception $e) {
                Log::warning('Deployment failed during update: ' . $e->getMessage());
            }

            $this->auditLog->log('mikrotik_updated', $mikrotik, [
                'vpn_ip_changed' => isset($data['vpn_ip']),
                'vpn_password_changed' => isset($data['vpn_password']) && $data['vpn_password'] !== '',
            ]);

            return $mikrotik->fresh();
        });
    }
        });
    }

    /**
     * Decommission MikroTik and release all resources.
     *
     * Uses forceDelete so the IP and port are immediately free for reuse —
     * soft-deleting would leave the unique index occupied and the next
     * provisioning could grab a colliding IP.
     */
    public function decommission(Mikrotik $mikrotik): void
    {
        DB::transaction(function () use ($mikrotik) {
            $vpnUsername = $mikrotik->vpn_username;
            $vpnIp = $mikrotik->vpn_ip;

            // Delete nginx mappings (hard delete — no soft deletes on this model)
            $mikrotik->nginxMappings()->delete();

            // Hard-delete the VPN user so the assigned_ip unique index is freed
            VpnUser::where('username', $vpnUsername)->forceDelete();

            // Decommission MikroTik
            $mikrotik->forceDelete();

            // Deploy cleaned config (best-effort)
            try {
                $this->chapSecretsDeploy->deploy();
                $this->nginxDeploy->deploy();
            } catch (\Exception $e) {
                Log::warning('Deployment failed during decommission: ' . $e->getMessage());
            }

            $this->auditLog->log('mikrotik_decommissioned', null, [
                'vpn_username' => $vpnUsername,
                'vpn_ip' => $vpnIp,
            ]);

            Log::info("MikroTik decommissioned: {$mikrotik->site_name} ({$vpnIp})");
        });
    }

    private function generateVpnUsername(string $siteName): string
    {
        $base = Str::slug($siteName, '-');
        $username = "mt-{$base}";

        // Ensure uniqueness
        $counter = 1;
        while (VpnUser::where('username', $username)->exists() ||
               Mikrotik::where('vpn_username', $username)->exists()) {
            $username = "mt-{$base}-{$counter}";
            $counter++;
        }

        return $username;
    }

    private function generatePassword(int $length = 16): string
    {
        return Str::random($length);
    }
}
