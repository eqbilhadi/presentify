<?php

declare(strict_types=1);

use App\Http\Controllers\Central\Rbac\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('rbac/central-users')
    ->name('central.rbac.users.')
    ->group(function (): void {
        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission:users.view')
            ->name('index');
        Route::get('/create', [UserController::class, 'create'])
            ->middleware('permission:users.create')
            ->name('create');
        Route::post('/', [UserController::class, 'store'])
            ->middleware('permission:users.create')
            ->name('store');
        Route::get('/{user}/edit', [UserController::class, 'edit'])
            ->middleware('permission:users.update')
            ->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.update')
            ->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete')
            ->name('destroy');
    });
