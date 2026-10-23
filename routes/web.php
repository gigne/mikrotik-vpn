<?php

declare(strict_types=1);

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MikrotikController;
use App\Http\Controllers\Web\VpnUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', '2fa'])->group(function () {
    // Profile (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Two-factor authentication (challenge endpoints exclude the 2fa middleware)
    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/activate', [TwoFactorController::class, 'activate'])->name('two-factor.activate');
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');

    // VPN users
    Route::resource('vpn-users', VpnUserController::class)
        ->except(['show']);
    Route::post('/vpn-users/{vpn_user}/toggle', [VpnUserController::class, 'toggle'])
        ->name('vpn-users.toggle');
    Route::post('/vpn-users/{vpn_user}/reset-password', [VpnUserController::class, 'resetPassword'])
        ->name('vpn-users.reset-password');
    Route::get('/vpn-users-export', [VpnUserController::class, 'export'])
        ->name('vpn-users.export');

    // MikroTik
    Route::resource('mikrotiks', MikrotikController::class);
    Route::post('/mikrotiks/{mikrotik}/check', [MikrotikController::class, 'check'])
        ->name('mikrotiks.check');
    Route::post('/mikrotiks/{mikrotik}/backup', [MikrotikController::class, 'backup'])
        ->name('mikrotiks.backup');
    Route::get('/mikrotiks/{mikrotik}/script/download', [MikrotikController::class, 'downloadScript'])
        ->name('mikrotiks.script.download');
});

// 2FA challenge endpoints: authenticated, but excluded from 2fa middleware
Route::middleware(['auth'])->group(function () {
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'challenge'])
        ->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [TwoFactorController::class, 'verifyChallenge'])
        ->name('two-factor.verify');
});

require __DIR__.'/auth.php';
