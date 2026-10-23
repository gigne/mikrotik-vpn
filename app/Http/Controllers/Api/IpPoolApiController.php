<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IpAllocatorService;
use App\Services\PortAllocatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IpPoolApiController extends Controller
{
    public function __construct(
        private readonly IpAllocatorService $ipAllocator,
    ) {}

    /**
     * GET /api/ip-pools - list IP pool status.
     */
    public function index(): JsonResponse
    {
        $stats = $this->ipAllocator->getStats();
        $usedIps = $this->ipAllocator->getUsedIps();

        $rangeStart = ip2long(config('vpnpanel.ip_pool.range_start'));
        $rangeEnd = ip2long(config('vpnpanel.ip_pool.range_end'));

        $entries = [];
        for ($ipLong = $rangeStart; $ipLong <= $rangeEnd; $ipLong++) {
            $ip = long2ip($ipLong);
            $entries[] = [
                'ip' => $ip,
                'status' => in_array($ip, $usedIps) ? 'used' : 'free',
            ];
        }

        return response()->json([
            'data' => [
                'stats' => $stats,
                'entries' => $entries,
            ],
        ]);
    }
}
