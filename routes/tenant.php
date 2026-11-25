<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SsoCallbackController;
use App\Http\Controllers\Rbac\RoleController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/sso/callback', SsoCallbackController::class)->name('tenant.sso.callback');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/settings/roles', [RoleController::class, 'index'])
            ->middleware('permission:settings.roles.view|manage_tenant')
            ->name('settings.roles');

        Route::get('/settings/roles/create', [RoleController::class, 'create'])
            ->middleware('permission:settings.roles.create|manage_tenant')
            ->name('settings.roles.create');

        Route::post('/settings/roles', [RoleController::class, 'store'])
            ->middleware('permission:settings.roles.create|manage_tenant')
            ->name('settings.roles.store');

        Route::get('/settings/roles/{role}/edit', [RoleController::class, 'edit'])
            ->middleware('permission:settings.roles.view|settings.roles.edit|manage_tenant')
            ->name('settings.roles.edit');

        Route::put('/settings/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:settings.roles.edit|manage_tenant')
            ->name('settings.roles.update');

        Route::delete('/settings/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:settings.roles.delete|manage_tenant')
            ->name('settings.roles.destroy');
    });
});
