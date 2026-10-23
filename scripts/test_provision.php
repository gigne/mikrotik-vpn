<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing provisioning ===\n";

$svc = app(App\Services\MikrotikProvisionService::class);

try {
    $m = $svc->provision(App\DTO\MikrotikData::fromArray(['site_name' => 'Test Debug']));
    echo "OK: " . $m->site_name . " / " . $m->vpn_ip . " / " . $m->vpn_username . "\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== chap-secrets ===\n";
echo file_get_contents('/etc/ppp/chap-secrets');

echo "\n=== stream.d/mikrotik.conf (first 10 lines) ===\n";
echo head(file_get_contents('/etc/nginx/stream.d/mikrotik.conf'), 10);

echo "\n=== Laravel log (chap/deploy related) ===\n";
$log = file_get_contents(storage_path('logs/laravel.log'));
$lines = explode("\n", $log);
foreach (array_slice($lines, -50) as $line) {
    if (preg_match('/chap|deploy|error|fail/i', $line)) {
        echo $line . "\n";
    }
}

function head($str, $n) {
    return implode("\n", array_slice(explode("\n", $str), 0, $n)) . "\n";
}
