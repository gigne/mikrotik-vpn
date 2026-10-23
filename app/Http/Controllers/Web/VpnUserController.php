<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\DTO\VpnUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\VpnUser\StoreVpnUserRequest;
use App\Http\Requests\VpnUser\UpdateVpnUserRequest;
use App\Models\VpnUser;
use App\Services\VpnProvisionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VpnUserController extends Controller
{
    public function __construct(
            private readonly VpnProvisionService $provisionService,
        ) {
            // Authorization is handled per-action via $this->authorize() calls
            // and form request policies.}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VpnUser::class);
        $vpnUsers = VpnUser::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                      ->orWhere('assigned_ip', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status') === 'active', fn ($q) => $q->active())
            ->when($request->query('status') === 'expired', fn ($q) => $q->expired())
            ->when($request->query('status') === 'disabled', fn ($q) => $q->where('is_enabled', false))
            ->orderBy('username')
            ->paginate(25);

        return view('vpn-users.index', compact('vpnUsers'));
    }

    public function create(): View
    {
        $this->authorize('create', VpnUser::class);

        return view('vpn-users.create');
    }

    public function store(StoreVpnUserRequest $request): RedirectResponse
    {
        $this->authorize('create', VpnUser::class);
        $data = $request->validated();
        if (empty($data['password'])) {
            $data['password'] = \Illuminate\Support\Str::password(16, symbols: false);
        }

        $vpnUser = $this->provisionService->create(VpnUserData::fromArray($data));

        return redirect()
            ->route('vpn-users.index')
            ->with('success', "VPN user '{$vpnUser->username}' created.");
    }

    public function edit(VpnUser $vpnUser): View
    {
        $this->authorize('update', $vpnUser);

        return view('vpn-users.edit', compact('vpnUser'));
    }

    public function update(UpdateVpnUserRequest $request, VpnUser $vpnUser): RedirectResponse
    {
        $this->authorize('update', $vpnUser);

        $data = $request->validated();
        $hasNewPassword = isset($data['password']) && $data['password'] !== '';

        $this->provisionService->update($vpnUser, VpnUserData::fromArray([
            'username' => $data['username'],
            'password' => $hasNewPassword ? $data['password'] : $vpnUser->password,
            'assignedIp' => $data['assignedIp'] ?? $vpnUser->assigned_ip,
            'notes' => $data['notes'],
            'expiredAt' => $data['expiredAt'],
            'isEnabled' => $data['isEnabled'],
        ]));

        return redirect()
            ->route('vpn-users.index')
            ->with('success', "VPN user '{$vpnUser->username}' updated.");
    }

    public function destroy(VpnUser $vpnUser): RedirectResponse
    {
        $this->authorize('delete', $vpnUser);

        $username = $vpnUser->username;
        $this->provisionService->delete($vpnUser);

        return redirect()
            ->route('vpn-users.index')
            ->with('success', "VPN user '{$username}' deleted.");
    }

    public function toggle(VpnUser $vpnUser): RedirectResponse
    {
            $this->authorize('update', $vpnUser);

            $vpnUser = $this->provisionService->toggle($vpnUser);

        return redirect()
            ->route('vpn-users.index')
            ->with('success', "VPN user '{$vpnUser->username}' " .
                ($vpnUser->is_enabled ? 'enabled' : 'disabled') . '.');
    }

    public function resetPassword(VpnUser $vpnUser): RedirectResponse
    {
            $this->authorize('update', $vpnUser);

            $password = \Illuminate\Support\Str::password(16, symbols: false);
        $vpnUser = $this->provisionService->resetPassword($vpnUser, $password);

        return redirect()
            ->route('vpn-users.index')
            ->with('success', "New password for '{$vpnUser->username}': {$password}");
    }

    public function export(): StreamedResponse
    {
            $this->authorize('viewAny', VpnUser::class);

            $filename = 'vpn-users-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['username', 'assigned_ip', 'is_enabled', 'expired_at', 'notes']);

            VpnUser::orderBy('username')->chunk(100, function ($users) use ($out) {
                foreach ($users as $user) {
                    fputcsv($out, [
                        $user->username,
                        $user->assigned_ip,
                        $user->is_enabled ? 'yes' : 'no',
                        $user->expired_at?->format('Y-m-d H:i:s'),
                        $user->notes,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
