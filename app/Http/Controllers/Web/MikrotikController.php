<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\DTO\MikrotikData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mikrotik\StoreMikrotikRequest;
use App\Http\Requests\Mikrotik\UpdateMikrotikRequest;
use App\Models\Mikrotik;
use App\Services\MikrotikBackupService;
use App\Services\MikrotikProvisionService;
use App\Services\RouterOSGeneratorService;
use App\Services\VpnMonitorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MikrotikController extends Controller
{
    public function __construct(
            private readonly MikrotikProvisionService $provisionService,
            private readonly RouterOSGeneratorService $routerOSGenerator,
            private readonly MikrotikBackupService $backupService,
            private readonly VpnMonitorService $monitor,
        ) {
            // Authorization is handled per-action via $this->authorize() calls
            // and form request policies, not authorizeResource() — the latter
            // depends on the controller middleware() method removed in Laravel 12.}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Mikrotik::class);

        $mikrotiks = Mikrotik::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('site_name', 'like', "%{$search}%")
                      ->orWhere('vpn_username', 'like', "%{$search}%")
                      ->orWhere('vpn_ip', 'like', "%{$search}%");
                });
            })
            ->orderBy('site_name')
            ->paginate(25);

        return view('mikrotiks.index', compact('mikrotiks'));
    }

    public function create(): View
    {
        $this->authorize('create', Mikrotik::class);

        return view('mikrotiks.create');
    }

    public function store(StoreMikrotikRequest $request): RedirectResponse
    {
        $this->authorize('create', Mikrotik::class);

        $mikrotik = $this->provisionService->provision(
            MikrotikData::fromArray($request->validated())
        );

        return redirect()
            ->route('mikrotiks.show', $mikrotik)
            ->with('success', "MikroTik '{$mikrotik->site_name}' provisioned. Apply the generated RouterOS script to the router.");
    }

    public function show(Mikrotik $mikrotik, Request $request): View
    {
        $this->authorize('view', $mikrotik);

        $script = $this->routerOSGenerator->generate($mikrotik);
        $summary = $this->routerOSGenerator->generateSummary($mikrotik);
        $backups = $this->backupService->listBackups($mikrotik);

        return view('mikrotiks.show', compact('mikrotik', 'script', 'summary', 'backups'));
    }

    public function edit(Mikrotik $mikrotik): View
    {
        $this->authorize('update', $mikrotik);

        return view('mikrotiks.edit', compact('mikrotik'));
    }

    public function update(UpdateMikrotikRequest $request, Mikrotik $mikrotik): RedirectResponse
    {
        $this->authorize('update', $mikrotik);

        $this->provisionService->update($mikrotik, $request->validated());

        return redirect()
            ->route('mikrotiks.show', $mikrotik)
            ->with('success', 'MikroTik updated.');
    }

    public function destroy(Mikrotik $mikrotik): RedirectResponse
    {
        $this->authorize('delete', $mikrotik);

        $siteName = $mikrotik->site_name;
        $this->provisionService->decommission($mikrotik);

        return redirect()
            ->route('mikrotiks.index')
            ->with('success', "MikroTik '{$siteName}' decommissioned.");
    }

    public function check(Mikrotik $mikrotik): RedirectResponse
    {
            $this->authorize('view', $mikrotik);

            $result = $this->monitor->checkMikrotik($mikrotik);

        $statusText = $result['status'] === 'online'
            ? "online ({$result['latency_ms']}ms)"
            : 'offline';

        return redirect()
            ->route('mikrotiks.index')
            ->with('success', "{$mikrotik->site_name}: {$statusText}");
    }

    public function backup(Mikrotik $mikrotik): RedirectResponse
    {
            $this->authorize('backup', $mikrotik);

            $result = $this->backupService->backup($mikrotik);

        if ($result === null) {
            return redirect()
                ->route('mikrotiks.show', $mikrotik)
                ->with('error', 'Backup failed, check logs.');
        }

        return redirect()
            ->route('mikrotiks.show', $mikrotik)
            ->with('success', 'Backup created: ' . $result['path']);
    }

    public function downloadScript(Mikrotik $mikrotik): StreamedResponse
    {
            $this->authorize('view', $mikrotik);

            $script = $this->routerOSGenerator->generate($mikrotik);
        $filename = Str::slug($mikrotik->site_name) . '-l2tp-client.rsc';

        return response()->streamDownload(
            fn () => print $script,
            $filename,
            ['Content-Type' => 'text/plain']
        );
    }
}
