<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SsoCallbackController;
use App\Http\Controllers\Tenant\Auth\SsoClientController;
use Illuminate\Support\Facades\Route;

Route::get('/sso/login', [SsoClientController::class, 'login'])->name('tenant.sso.login');
Route::get('/sso/callback', SsoCallbackController::class)->name('tenant.sso.callback');

Route::middleware(['auth'])->group(function () {
    Route::post('/sso/logout', [SsoClientController::class, 'logout'])->name('tenant.sso.logout');
    Route::post('/sso/switch', [SsoClientController::class, 'switch'])->name('tenant.sso.switch');
});
