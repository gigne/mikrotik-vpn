<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Mikrotik;
use App\Services\VpnMonitorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pings a single MikroTik and persists its status.
 */
class MonitorMikrotikJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $mikrotikId) {}

    public function handle(VpnMonitorService $monitor): void
    {
        $mikrotik = Mikrotik::find($this->mikrotikId);

        if ($mikrotik === null) {
            return;
        }

        $monitor->checkMikrotik($mikrotik);
    }
}