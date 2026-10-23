<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ProcessResult;
use App\Contracts\ProcessRunner;
use Symfony\Component\Process\Process;

/**
 * Runs commands on the same machine via Symfony Process.
 */
final class LocalProcessRunner implements ProcessRunner
{
    /** @var list<ProcessResult> */
    private array $executed = [];

    public function __construct(private readonly int $timeout = 60)
    {
    }

    public function run(array $command, ?int $timeout = null): ProcessResult
    {
        $start = microtime(true);
        $process = new Process($command, timeout: $timeout ?? $this->timeout);
        $process->run();
        $elapsed = (microtime(true) - $start) * 1000.0;

        $result = new ProcessResult(
            command: $command,
            exitCode: $process->getExitCode() ?? 0,
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
            durationMs: $elapsed,
        );

        $this->executed[] = $result;

        return $result;
    }

    public function canRun(string $binary): bool
    {
        $process = new Process(['which', $binary], timeout: 5);
        $process->run();
        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    /** @return list<ProcessResult> for tests only. */
    public function getExecuted(): array
    {
        return $this->executed;
    }
}
