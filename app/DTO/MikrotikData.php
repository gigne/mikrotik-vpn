<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data transfer object for provisioning a MikroTik branch.
 */
final class MikrotikData
{
    public function __construct(
        public readonly string $siteName,
        public readonly ?string $description = null,
        public readonly ?string $vpnIp = null,
        public readonly ?string $vpnPassword = null,
        public readonly ?string $vpnUsername = null,
        public readonly int $winboxInternalPort = 8291,
        public readonly int $webfigInternalPort = 80,
        public readonly ?int $winboxPublicPort = null,
        public readonly ?int $webfigPublicPort = null,
        public readonly ?string $routerosVersion = null,
        public readonly ?string $sshPublicKey = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            siteName: $data['site_name'],
            description: $data['description'] ?? null,
            vpnIp: $data['vpn_ip'] ?? null,
            vpnPassword: $data['vpn_password'] ?? null,
            vpnUsername: $data['vpn_username'] ?? null,
            winboxInternalPort: (int) ($data['winbox_internal_port'] ?? 8291),
            webfigInternalPort: (int) ($data['webfig_internal_port'] ?? 80),
            winboxPublicPort: isset($data['winbox_public_port']) ? (int) $data['winbox_public_port'] : null,
            webfigPublicPort: isset($data['webfig_public_port']) ? (int) $data['webfig_public_port'] : null,
            routerosVersion: $data['routeros_version'] ?? null,
            sshPublicKey: $data['ssh_public_key'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'site_name' => $this->siteName,
            'description' => $this->description,
            'vpn_ip' => $this->vpnIp,
            'vpn_username' => $this->vpnUsername,
            'winbox_internal_port' => $this->winboxInternalPort,
            'webfig_internal_port' => $this->webfigInternalPort,
            'winbox_public_port' => $this->winboxPublicPort,
            'webfig_public_port' => $this->webfigPublicPort,
            'routeros_version' => $this->routerosVersion,
            'ssh_public_key' => $this->sshPublicKey,
        ], fn ($v) => $v !== null);
    }
}
