<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\DTO\Ikev2UserData;
use App\Http\Controllers\Controller;
use App\Models\Ikev2User;
use App\Services\Ikev2ProvisionService;
use App\Services\Ikev2CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class Ikev2UserController extends Controller
{
    public function __construct(
        private readonly Ikev2ProvisionService $provisionService,
        private readonly Ikev2CertificateService $certService,
    ) {
    }

    /**
     * Display IKEv2 users list.
     */
    public function index(): View
    {
        $users = Ikev2User::orderByDesc('created_at')->paginate(20);

        return view('ikev2-users.index', compact('users'));
    }

    /**
     * Show create form.
     */
    public function create(): View
    {
        return view('ikev2-users.create');
    }

    /**
     * Store a new IKEv2 user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:ikev2_users,username',
            'password' => 'required|string|min:8|max:64',
            'assigned_ip' => 'nullable|ipv4',
            'notes' => 'nullable|string|max:1000',
            'expired_at' => 'nullable|date|after:today',
            'is_enabled' => 'nullable|boolean',
        ]);

        try {
            $data = new Ikev2UserData(
                username: $validated['username'],
                password: $validated['password'],
                assignedIp: $validated['assigned_ip'] ?? null,
                notes: $validated['notes'] ?? null,
                expiredAt: isset($validated['expired_at']) ? now()->parse($validated['expired_at']) : null,
                isEnabled: !empty($validated['is_enabled']),
            );

            $this->provisionService->create($data);

                        return redirect()->route('ikev2-users.index')
                            ->with('success', 'IKEv2 user created successfully');
                    } catch (\Throwable $e) {
                        Log::error('IKEv2 store failed: ' . $e->getMessage(), $validated);

                        return back()
                            ->withInput()
                            ->withErrors(['message' => 'Failed to create IKEv2 user: ' . $e->getMessage()]);
                    }
                }

    /**
     * Show user details.
     */
    public function show(Ikev2User $ikev2User): View
    {
        return view('ikev2-users.show', compact('ikev2User'));
    }

    /**
     * Show edit form.
     */
    public function edit(Ikev2User $ikev2User): View
    {
        return view('ikev2-users.edit', compact('ikev2User'));
    }

    /**
     * Update IKEv2 user.
     */
    public function update(Request $request, Ikev2User $ikev2User): RedirectResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:ikev2_users,username,' . $ikev2User->id,
            'password' => 'nullable|string|min:8|max:64',
            'assigned_ip' => 'nullable|ipv4',
            'notes' => 'nullable|string|max:1000',
            'expired_at' => 'nullable|date|after:today',
            'is_enabled' => 'nullable|boolean',
        ]);

        try {
            $data = new Ikev2UserData(
                username: $validated['username'],
                password: $validated['password'] ?? '',
                assignedIp: $validated['assigned_ip'] ?? null,
                notes: $validated['notes'] ?? null,
                expiredAt: isset($validated['expired_at']) ? now()->parse($validated['expired_at']) : null,
                isEnabled: !empty($validated['is_enabled']),
            );

            $this->provisionService->update($ikev2User, $data);

            return redirect()->route('ikev2-users.show', $ikev2User)
                ->with('success', 'IKEv2 user updated successfully');
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                            ->withErrors(['message' => 'Failed to update IKEv2 user: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete IKEv2 user.
     */
    public function destroy(Ikev2User $ikev2User): RedirectResponse
    {
        try {
            $this->provisionService->delete($ikev2User);

            return redirect()->route('ikev2-users.index')
                ->with('success', 'IKEv2 user deleted successfully');
        } catch (\Throwable $e) {
            return back()
                ->with('error', 'Failed to delete IKEv2 user: ' . $e->getMessage());
        }
    }

    /**
     * Download .mobileconfig profile.
     */
    public function downloadProfile(Ikev2User $ikev2User, Request $request)
    {
        if (!$ikev2User->has_certificate) {
            abort(404, 'Certificate not available');
        }

        $serverAddress = config('vpnpanel.domain', 'vpn.example.com');
        $mobileConfig = $this->certService->generateMobileConfig($ikev2User, $serverAddress);

        return response($mobileConfig, 200, [
            'Content-Type' => 'application/x-apple-aspen-configuration',
            'Content-Disposition' => 'attachment; filename="' . $ikev2User->username . '.mobileconfig"',
        ]);
    }

    /**
     * Regenerate certificate.
     */
    public function regenerateCertificate(Ikev2User $ikev2User): RedirectResponse
    {
        try {
            $this->provisionService->regenerateCertificate($ikev2User);

            return back()->with('success', 'Certificate regenerated successfully');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to regenerate certificate: ' . $e->getMessage());
        }
    }
}
