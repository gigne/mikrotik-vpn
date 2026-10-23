<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ProcessResult;
use App\Contracts\ProcessRunner;
use Symfony\Component\Process\Process;

/**
 * Runs commands on a remote VPN server over SSH.
 *
 * Commands are passed as argv to avoid shell injection; they are assembled
 * into a single SSH invocation that uses the configured identity file.
 */
final class SshProcessRunner implements ProcessRunner
{
    public function __construct(
        private readonly string $target,
        private readonly string $keyPath,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
    ) {
    }

    public function run(array $command, ?int $timeout = null): ProcessResult
    {
        $remote = implode(' ', array_map('escapeshellarg', $command));
        $argv = [
            'ssh',
            '-i', $this->keyPath,
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=' . (string) $this->connectTimeout,
            '-o', 'StrictHostKeyChecking=accept-new',
            $this->target,
            $remote,
        ];

        $start = microtime(true);
        $process = new Process($argv, timeout: $timeout ?? $this->timeout);
        $process->run();
        $elapsed = (microtime(true) - $start) * 1000.0;

        return new ProcessResult(
            command: $command,
            exitCode: $process->getExitCode() ?? 0,
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
            durationMs: $elapsed,
        );
    }

    public function canRun(string $binary): bool
    {
        $process = new Process(
            ['ssh', $this->target, 'command -v ' . escapeshellarg($binary)],
            timeout: $this->connectTimeout
        );
        $process->run();
        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }
}
