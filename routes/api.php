<?php

declare(strict_types=1);

use App\Http\Controllers\Api\IpPoolApiController;
use App\Http\Controllers\Api\MikrotikApiController;
use App\Http\Controllers\Api\SystemApiController;
use App\Http\Controllers\Api\VpnUserApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->group(function () {
    // MikroTik management
    Route::get('/mikrotik', [MikrotikApiController::class, 'index']);
    Route::post('/mikrotik', [MikrotikApiController::class, 'store']);
    Route::get('/mikrotik/{mikrotik}', [MikrotikApiController::class, 'show']);
    Route::put('/mikrotik/{mikrotik}', [MikrotikApiController::class, 'update']);
    Route::delete('/mikrotik/{mikrotik}', [MikrotikApiController::class, 'destroy']);
    Route::get('/mikrotik/{mikrotik}/script', [MikrotikApiController::class, 'showScript']);

    // VPN user management
    Route::get('/vpn-users', [VpnUserApiController::class, 'index']);
    Route::post('/vpn-users', [VpnUserApiController::class, 'store']);
    Route::get('/vpn-users/{vpnUser}', [VpnUserApiController::class, 'show']);
    Route::put('/vpn-users/{vpnUser}', [VpnUserApiController::class, 'update']);
    Route::delete('/vpn-users/{vpnUser}', [VpnUserApiController::class, 'destroy']);
    Route::post('/vpn-users/{vpnUser}/toggle', [VpnUserApiController::class, 'toggle']);

    // IP pool management
    Route::get('/ip-pools', [IpPoolApiController::class, 'index']);

    // System operations
    Route::post('/reload-nginx', [SystemApiController::class, 'reloadNginx']);
    Route::post('/restart-xl2tpd', [SystemApiController::class, 'restartXl2tpd']);
    Route::get('/status', [SystemApiController::class, 'status']);
    Route::get('/dashboard', [SystemApiController::class, 'dashboard']);
});
