<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Abstraction over "run a command on the VPN server".
 *
 * Implementations:
 *  - LocalProcessRunner : Symfony Process on this machine (production, Ubuntu).
 *  - SshProcessRunner   : ssh to a remote VPN host.
 *  - FakeProcessRunner  : records commands, executes nothing (tests/sandbox).
 */
interface ProcessRunner
{
    /**
     * Execute a command without shell interpolation.
     *
     * @param  list<string>  $command  argv array; never pass a shell string
     * @param  int|null  $timeout  seconds
     */
    public function run(array $command, ?int $timeout = null): ProcessResult;

    /** Whether the given binary exists on the target system. */
    public function canRun(string $binary): bool;
}
