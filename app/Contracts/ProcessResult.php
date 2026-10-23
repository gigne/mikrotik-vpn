<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Immutable result of a command executed by a ProcessRunner.
 */
final class ProcessResult
{
    /**
     * @param  list<string>  $command
     */
    public function __construct(
        public readonly array $command,
        public readonly int $exitCode,
        public readonly string $stdout = '',
        public readonly string $stderr = '',
        public readonly ?float $durationMs = null,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    /** Flattened output, useful for logging and notifications. */
    public function output(): string
    {
        return trim($this->stdout . ($this->stderr !== '' ? "\n" . $this->stderr : ''));
    }

    /** Shell-safe rendering of the command (argv joined with spaces). */
    public function commandLine(): string
    {
        return implode(' ', array_map(
            static fn (string $arg): string => str_contains($arg, ' ') ? escapeshellarg($arg) : $arg,
            $this->command
        ));
    }

    /** A result representing a command that was never executed. */
    public static function skipped(array $command, string $reason): self
    {
        return new self($command, 0, '', "skipped: {$reason}");
    }
}
