<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ChapSecretsDeployService;
use App\Services\NginxDeployService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deploys both managed files in the background so that HTTP requests stay fast.
 */
class DeployConfigsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly bool $chapSecrets = true,
        public readonly bool $nginx = true,
    ) {}

    public function handle(
        ChapSecretsDeployService $chapSecrets,
        NginxDeployService $nginx,
    ): void {
        if ($this->chapSecrets) {
            $chapSecrets->deploy();
            Log::info('chap-secrets deployed by queue job');
        }

        if ($this->nginx) {
            $nginx->deploy();
            Log::info('nginx config deployed by queue job');
        }
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('DeployConfigsJob failed: ' . ($e?->getMessage() ?? 'unknown'));
    }
}