<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChapSecretsDeployService;
use App\Services\NginxDeployService;
use App\Services\VpnMonitorService;
use Illuminate\Http\JsonResponse;

class SystemApiController extends Controller
{
    public function __construct(
        private readonly ChapSecretsDeployService $chapSecrets,
        private readonly NginxDeployService $nginx,
        private readonly VpnMonitorService $monitor,
    ) {}

    /**
     * POST /api/reload-nginx - regenerate and reload nginx.
     */
    public function reloadNginx(): JsonResponse
    {
        $result = $this->nginx->deploy();

        return response()->json([
            'message' => 'Nginx reloaded',
            'data' => $result,
        ]);
    }

    /**
     * POST /api/restart-xl2tpd - restart xl2tpd.
     */
    public function restartXl2tpd(): JsonResponse
    {
        $result = $this->chapSecrets->restartXl2tpd();

        if (! $result->successful()) {
            return response()->json([
                'message' => 'Failed to restart xl2tpd',
                'error' => $result->output(),
            ], 500);
        }

        return response()->json(['message' => 'xl2tpd restarted']);
    }

    /**
     * GET /api/status - VPN server + services status.
     */
    public function status(): JsonResponse
    {
        $status = $this->monitor->checkVpnServerStatus();

        return response()->json(['data' => $status]);
    }

    /**
     * GET /api/dashboard - dashboard statistics.
     */
    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => $this->monitor->getStats()]);
    }
}
