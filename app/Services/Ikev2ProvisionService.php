<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ikev2User;
use App\Services\Ikev2CertificateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Provisions IKEv2 users with certificates and mobile profiles.
 */
class Ikev2ProvisionService
{
    public function __construct(
        private readonly Ikev2CertificateService $certService,
        private readonly Ikev2ConfigDeployService $configDeploy,
        private readonly AuditLogService $auditLog,
    ) {
    }

    /**
     * Create an IKEv2 user with certificate and profile.
     */
    public function create(\App\DTO\Ikev2UserData $data): Ikev2User
    {
        return DB::transaction(function () use ($data): Ikev2User {
            if (Ikev2User::where('username', $data->username)->exists()) {
                throw new \RuntimeException("IKEv2 user '{$data->username}' already exists");
            }

            $user = Ikev2User::create([
                'username' => $data->username,
                'password' => $data->password,
                'assigned_ip' => $data->assignedIp,
                'notes' => $data->notes,
                'expired_at' => $data->expiredAt,
                'is_enabled' => $data->isEnabled,
            ]);

            // Generate certificate untuk user ini
            $certData = $this->certService->generateClientCertificate($user);
            $user->update($certData);

            $this->deployConfig();

            $this->auditLog->log('ikev2_user_created', $user, [
                'username' => $user->username,
                'assigned_ip' => $user->assigned_ip,
                'certificate_serial' => $user->certificate_serial,
            ]);

            Log::info("IKEv2 user created: {$user->username} ({$user->assigned_ip})");

            return $user->fresh();
        });
    }

    /**
     * Update an IKEv2 user and redeploy config.
     */
    public function update(Ikev2User $user, \App\DTO\Ikev2UserData $data): Ikev2User
    {
        return DB::transaction(function () use ($user, $data): Ikev2User {
            $user->update([
                'username' => $data->username,
                'password' => $data->password ?: $user->password,
                'assigned_ip' => $data->assignedIp ?? $user->assigned_ip,
                'notes' => $data->notes,
                'expired_at' => $data->expiredAt,
                'is_enabled' => $data->isEnabled,
            ]);

            $this->deployConfig();

            return $user->fresh();
        });
    }

    /**
     * Delete an IKEv2 user.
     */
    public function delete(Ikev2User $user): void
    {
        DB::transaction(function () use ($user): void {
            $username = $user->username;
            $ip = $user->assigned_ip;

            $user->forceDelete();

            $this->deployConfig();

            $this->auditLog->log('ikev2_user_deleted', null, [
                'username' => $username,
                'assigned_ip' => $ip,
            ]);

            Log::info("IKEv2 user deleted: {$username} ({$ip})");
        });
    }

    /**
     * Regenerate certificate untuk user.
     */
    public function regenerateCertificate(Ikev2User $user): Ikev2User
    {
        return DB::transaction(function () use ($user): Ikev2User {
            $certData = $this->certService->generateClientCertificate($user);
            $user->update($certData);

            $this->auditLog->log('ikev2_certificate_regenerated', $user, [
                'certificate_serial' => $user->certificate_serial,
            ]);

            return $user->fresh();
        });
    }

    private function deployConfig(): void
    {
        try {
            $this->configDeploy->deploy();
                } catch (\Throwable $e) {
            Log::warning('IKEv2 config deploy failed: ' . $e->getMessage());
        }
    }
}
