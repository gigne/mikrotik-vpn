<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find and force-delete any orphaned VPN users for deleted MikroTik sites
$orphaned = App\Models\VpnUser::onlyTrashed()->get();
echo "Orphaned (soft-deleted) VPN users: {$orphaned->count()}\n";
foreach ($orphaned as $u) {
    echo "  force-deleting: {$u->username} ({$u->assigned_ip})\n";
    $u->forceDelete();
}

// Also check for VPN users whose username matches a soft-deleted MikroTik
$mikrotikUsernames = App\Models\Mikrotik::onlyTrashed()->pluck('vpn_username')->all();
if (!empty($mikrotikUsernames)) {
    $orphans2 = App\Models\VpnUser::whereIn('username', $mikrotikUsernames)->get();
    foreach ($orphans2 as $u) {
        echo "  force-deleting orphan: {$u->username} ({$u->assigned_ip})\n";
        $u->forceDelete();
    }
}

// Check all VPN users and their IPs
echo "\n=== Current VPN users ===\n";
foreach (App\Models\VpnUser::orderBy('username')->get() as $u) {
    echo "  {$u->username} / {$u->assigned_ip} / enabled={$u->is_enabled}\n";
}

echo "\n=== Current Mikrotiks ===\n";
foreach (App\Models\Mikrotik::orderBy('site_name')->get() as $m) {
    echo "  {$m->site_name} / {$m->vpn_ip} / {$m->vpn_username}\n";
}

// Re-deploy configs
echo "\n=== Re-deploying ===\n";
try {
    app(App\Services\ChapSecretsDeployService::class)->deploy();
    echo "chap-secrets: OK\n";
} catch (Exception $e) {
    echo "chap-secrets FAIL: {$e->getMessage()}\n";
}
try {
    app(App\Services\NginxDeployService::class)->deploy();
    echo "nginx: OK\n";
} catch (Exception $e) {
    echo "nginx FAIL: {$e->getMessage()}\n";
}
