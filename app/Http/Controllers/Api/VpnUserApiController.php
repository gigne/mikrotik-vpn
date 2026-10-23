<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTO\VpnUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\VpnUser\StoreVpnUserRequest;
use App\Http\Requests\VpnUser\UpdateVpnUserRequest;
use App\Models\VpnUser;
use App\Services\VpnProvisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class VpnUserApiController extends Controller
{
    public function __construct(
        private readonly VpnProvisionService $provisionService,
    ) {}

    /**
     * GET /api/vpn-users - list VPN users.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $vpnUsers = VpnUser::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                      ->orWhere('assigned_ip', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status') === 'active', function ($query) {
                $query->active();
            })
            ->when($request->query('status') === 'expired', function ($query) {
                $query->expired();
            })
            ->orderBy('username')
            ->paginate($request->integer('per_page', 25));

        return JsonResource::collection($vpnUsers);
    }

    /**
     * POST /api/vpn-users - create a VPN user.
     */
    public function store(StoreVpnUserRequest $request): JsonResponse
    {
        $vpnUser = $this->provisionService->create(VpnUserData::fromArray($request->validated()));

        return (new JsonResource($vpnUser))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/vpn-users/{vpnUser} - show a VPN user.
     */
    public function show(VpnUser $vpnUser): JsonResource
    {
        return new JsonResource($vpnUser);
    }

    /**
     * PUT /api/vpn-users/{vpnUser} - update a VPN user.
     */
    public function update(UpdateVpnUserRequest $request, VpnUser $vpnUser): JsonResource
    {
        $updated = $this->provisionService->update($vpnUser, VpnUserData::fromArray($request->validated()));

        return new JsonResource($updated);
    }

    /**
     * DELETE /api/vpn-users/{vpnUser} - delete a VPN user.
     */
    public function destroy(VpnUser $vpnUser): JsonResponse
    {
        $this->provisionService->delete($vpnUser);

        return response()->json(['message' => 'VPN user deleted'], 200);
    }

    /**
     * POST /api/vpn-users/{vpnUser}/toggle - enable/disable a VPN user.
    */
    public function toggle(VpnUser $vpnUser): JsonResource
    {
        $toggled = $this->provisionService->toggle($vpnUser);

        return new JsonResource($toggled);
    }
}
