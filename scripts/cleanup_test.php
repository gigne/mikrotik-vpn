<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Delete the test-debug site we created via the CLI test
$m = App\Models\Mikrotik::where('site_name', 'Test Debug')->first();
if ($m) {
    $m->nginxMappings()->delete();
    App\Models\VpnUser::where('username', $m->vpn_username)->delete();
    $m->delete();
    echo "Deleted: {$m->site_name}\n";
}

// Re-deploy chap-secrets + nginx to remove the deleted entry
app(App\Services\ChapSecretsDeployService::class)->deploy();
app(App\Services\NginxDeployService::class)->deploy();

echo "\n=== chap-secrets ===\n";
echo file_get_contents('/etc/ppp/chap-secrets');

echo "\n=== DB mikrotiks ===\n";
foreach (App\Models\Mikrotik::orderBy('site_name')->get() as $mk) {
    echo "  {$mk->site_name} / {$mk->vpn_ip} / {$mk->vpn_username} / winbox:{$mk->winbox_public_port}\n";
}
