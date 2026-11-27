<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\TenantProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/tenant-assets/{path}', [TenantProfileController::class, 'asset'])
    ->where('path', '.*')
    ->name('tenant.branding.asset');

Route::middleware(['auth', 'verified'])
    ->prefix('tenant/profile')
    ->name('tenant.profile.')
    ->group(function (): void {
        Route::get('/', [TenantProfileController::class, 'edit'])
            ->middleware('permission:tenant_profile.view')
            ->name('edit');
        Route::put('/', [TenantProfileController::class, 'update'])
            ->middleware('permission:tenant_profile.update')
            ->name('update');
        Route::post('/branding', [TenantProfileController::class, 'uploadLogo'])
            ->middleware('permission:tenant_profile.update')
            ->name('branding.upload');
        Route::delete('/branding/{type}', [TenantProfileController::class, 'destroyLogo'])
            ->whereIn('type', ['logo', 'favicon'])
            ->middleware('permission:tenant_profile.update')
            ->name('branding.destroy');
    });
