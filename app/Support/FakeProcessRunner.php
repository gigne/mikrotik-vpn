<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ProcessResult;
use App\Contracts\ProcessRunner;

/**
 * Records commands without executing them.
 *
 * Used by the sandbox driver and by tests. Every run() returns a
 * ProcessResult with exit code 0 and a predictable stdout.
 */
final class FakeProcessRunner implements ProcessRunner
{
    /** @var list<list<string>> */
    private array $executed = [];

    /** @var array<string, ProcessResult> explicit responses keyed by command-line */
    private array $responses = [];

    public function run(array $command, ?int $timeout = null): ProcessResult
    {
        $this->executed[] = $command;

        $key = implode(' ', $command);
        if (isset($this->responses[$key])) {
            return $this->responses[$key];
        }

        return new ProcessResult(
            command: $command,
            exitCode: 0,
            stdout: 'fake',
            stderr: '',
        );
    }

    public function canRun(string $binary): bool
    {
        return true;
    }

    /** Force a specific result for a command (tests). */
    public function setResponse(array $command, ProcessResult $result): void
    {
        $this->responses[implode(' ', $command)] = $result;
    }

    /** @return list<list<string>> */
    public function getExecuted(): array
    {
        return $this->executed;
    }

    public function wasCalled(string $binary): bool
    {
        foreach ($this->executed as $cmd) {
                if (in_array($binary, $cmd, true)) {
                return true;
            }
        }
        return false;
    }

    public function reset(): void
    {
        $this->executed = [];
        $this->responses = [];
    }
}
