<?php

use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Central\Auth\SsoAuthorizeController;
use App\Http\Controllers\Central\Auth\SsoLogoutController;
use App\Http\Controllers\Central\Auth\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('workspace', [WorkspaceController::class, 'index'])->name('workspace.picker');
    Route::post('workspace/switch', [WorkspaceController::class, 'switch'])->name('workspace.switch');
    Route::post('logout-all', [WorkspaceController::class, 'logout'])->name('logout.slo');

    Route::get('auth/sso/authorize', SsoAuthorizeController::class)
        ->middleware('throttle:30,1')
        ->name('central.sso.authorize');
});

Route::get('auth/sso/logout', SsoLogoutController::class)
    ->middleware(['signed:relative'])
    ->name('central.sso.logout');

Route::middleware(['guest'])->group(function () {
    Route::get('auth/otp', [OtpVerificationController::class, 'show'])->name('auth.otp.show');
    Route::post('auth/otp', [OtpVerificationController::class, 'store'])->name('auth.otp.store');
    Route::post('auth/otp/send', [OtpVerificationController::class, 'send'])->name('auth.otp.send');
});
