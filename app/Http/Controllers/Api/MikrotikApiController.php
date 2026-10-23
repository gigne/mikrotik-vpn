<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTO\MikrotikData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mikrotik\StoreMikrotikRequest;
use App\Http\Requests\Mikrotik\UpdateMikrotikRequest;
use App\Models\Mikrotik;
use App\Services\MikrotikProvisionService;
use App\Services\RouterOSGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class MikrotikApiController extends Controller
{
    public function __construct(
        private readonly MikrotikProvisionService $provisionService,
        private readonly RouterOSGeneratorService $routerOSGenerator,
    ) {}

    /**
     * GET /api/mikrotik - list MikroTik devices.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $mikrotiks = Mikrotik::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('site_name', 'like', "%{$search}%")
                      ->orWhere('vpn_username', 'like', "%{$search}%")
                      ->orWhere('vpn_ip', 'like', "%{$search}%");
                });
            })
                        ->when($request->boolean('online') === true, function ($query) {
                $query->where('is_online', true);
            })
            ->orderBy('site_name')
            ->paginate($request->integer('per_page', 25));

        return JsonResource::collection($mikrotiks);
    }

    /**
     * POST /api/mikrotik - provision a new MikroTik.
     */
    public function store(StoreMikrotikRequest $request): JsonResponse
    {
        $mikrotik = $this->provisionService->provision(
            MikrotikData::fromArray($request->validated())
        );

        return (new JsonResource($mikrotik))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/mikrotik/{mikrotik} - show a MikroTik.
     */
    public function show(Mikrotik $mikrotik): JsonResource
    {
        return new JsonResource($mikrotik);
    }

    /**
     * PUT /api/mikrotik/{mikrotik} - update a MikroTik.
     */
    public function update(UpdateMikrotikRequest $request, Mikrotik $mikrotik): JsonResource
    {
        $updated = $this->provisionService->update($mikrotik, $request->validated());

        return new JsonResource($updated);
    }

    /**
     * DELETE /api/mikrotik/{mikrotik} - decommission a MikroTik.
     */
    public function destroy(Mikrotik $mikrotik): JsonResponse
    {
        $this->provisionService->decommission($mikrotik);

        return response()->json(['message' => 'MikroTik decommissioned'], 200);
    }

    /**
     * GET /api/mikrotik/{mikrotik}/script - generated RouterOS script.
     */
    public function showScript(Mikrotik $mikrotik): JsonResponse
    {
        return response()->json([
            'data' => [
                'script' => $this->routerOSGenerator->generate($mikrotik),
                'qr_data' => $this->routerOSGenerator->generateQRData($mikrotik),
                'summary' => $this->routerOSGenerator->generateSummary($mikrotik),
            ],
        ]);
    }
}
