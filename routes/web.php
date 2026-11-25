<?php

use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Auth\WorkspaceController;
use App\Http\Middleware\PreventAccessFromTenantDomains;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')
    ->middleware(PreventAccessFromTenantDomains::class)
    ->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', PreventAccessFromTenantDomains::class])->group(function () {
    Route::get('workspace', [WorkspaceController::class, 'index'])->name('workspace.picker');
    Route::post('workspace/switch', [WorkspaceController::class, 'switch'])->name('workspace.switch');
});

Route::post('logout-all', [WorkspaceController::class, 'logout'])
    ->name('logout.slo')
    ->middleware('auth');

Route::get('sso/logout', [WorkspaceController::class, 'sloCallback'])
    ->middleware(['signed:relative', PreventAccessFromTenantDomains::class])
    ->name('logout.slo.callback');

Route::middleware(['guest', PreventAccessFromTenantDomains::class])->group(function () {
    Route::get('auth/otp', [OtpVerificationController::class, 'show'])->name('auth.otp.show');
    Route::post('auth/otp', [OtpVerificationController::class, 'store'])->name('auth.otp.store');
    Route::post('auth/otp/send', [OtpVerificationController::class, 'send'])->name('auth.otp.send');
});

require __DIR__.'/settings.php';
