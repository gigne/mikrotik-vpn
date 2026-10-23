<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data transfer object for creating a VPN user.
 */
final class VpnUserData
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly ?string $assignedIp = null,
        public readonly ?string $notes = null,
        public readonly ?string $expiredAt = null,
        public readonly bool $isEnabled = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            username: $data['username'],
                password: $data['password'] ?? '',
            assignedIp: $data['assigned_ip'] ?? null,
            notes: $data['notes'] ?? null,
            expiredAt: $data['expired_at'] ?? null,
            isEnabled: (bool) ($data['is_enabled'] ?? true),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
            'assigned_ip' => $this->assignedIp,
            'notes' => $this->notes,
            'expired_at' => $this->expiredAt,
            'is_enabled' => $this->isEnabled,
        ];
    }
}
