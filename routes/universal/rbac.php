<?php

declare(strict_types=1);

use App\Http\Controllers\Universal\Rbac\MenuActionController;
use App\Http\Controllers\Universal\Rbac\MenuController;
use App\Http\Controllers\Universal\Rbac\PermissionController;
use App\Http\Controllers\Universal\Rbac\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('rbac')->name('rbac.')->group(function (): void {
    // Menus
    Route::get('/menus', [MenuController::class, 'index'])
        ->middleware('permission:menus.view')
        ->name('menus.index');
    Route::get('/menus/create', [MenuController::class, 'create'])
        ->middleware('permission:menus.create')
        ->name('menus.create');
    Route::post('/menus', [MenuController::class, 'store'])
        ->middleware('permission:menus.create')
        ->name('menus.store');
    Route::get('/menus/sort', [MenuController::class, 'sort'])
        ->middleware('permission:menus.update')
        ->name('menus.sort');
    Route::post('/menus/reorder', [MenuController::class, 'reorder'])
        ->middleware('permission:menus.update')
        ->name('menus.reorder');
    Route::get('/menus/{menu}/edit', [MenuController::class, 'edit'])
        ->middleware('permission:menus.update')
        ->name('menus.edit');
    Route::put('/menus/{menu}', [MenuController::class, 'update'])
        ->middleware('permission:menus.update')
        ->name('menus.update');
    Route::patch('/menus/{menu}/toggle-status', [MenuController::class, 'toggleStatus'])
        ->middleware('permission:menus.update')
        ->name('menus.toggle-status');
    Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])
        ->middleware('permission:menus.delete')
        ->name('menus.destroy');

    // Menu Actions
    Route::post('/menus/{menu}/actions', [MenuActionController::class, 'store'])
        ->middleware('permission:permissions.create')
        ->name('menu-actions.store');
    Route::put('/menu-actions/{menuAction}', [MenuActionController::class, 'update'])
        ->middleware('permission:permissions.update')
        ->name('menu-actions.update');
    Route::delete('/menu-actions/{menuAction}', [MenuActionController::class, 'destroy'])
        ->middleware('permission:permissions.delete')
        ->name('menu-actions.destroy');

    // Standalone Permissions
    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware('permission:permissions.view')
        ->name('permissions.index');
    Route::post('/permissions', [PermissionController::class, 'store'])
        ->middleware('permission:permissions.create')
        ->name('permissions.store');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])
        ->middleware('permission:permissions.update')
        ->name('permissions.update');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])
        ->middleware('permission:permissions.delete')
        ->name('permissions.destroy');

    // Roles
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])
        ->middleware('permission:roles.create')
        ->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:roles.create')
        ->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
        ->middleware('permission:roles.update')
        ->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:roles.update')
        ->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:roles.delete')
        ->name('roles.destroy');
});
