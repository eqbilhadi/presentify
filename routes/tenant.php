<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SsoCallbackController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/sso/callback', SsoCallbackController::class)->name('tenant.sso.callback');
});
