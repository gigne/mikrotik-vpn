<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Prefixes system commands with sudo when the panel runs as a non-root user
 * (production "local" driver). The sudoers rule installed by
 * deploy/ubuntu-24.04-install.sh only allows the exact commands the panel
 * needs: systemctl restart xl2tpd/ipsec, systemctl reload nginx, nginx -t.
 */
final class CommandBuilder
{
    /**
     * @param  list<string>  $command
     * @return list<string>
     */
    public function build(array $command): array
    {
        if (config('vpnpanel.process_sudo', true)) {
            return ['sudo', ...$command];
        }

        return $command;
    }
}
