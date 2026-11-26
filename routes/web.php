<?php

use App\Http\Middleware\PreventAccessFromTenantDomains;
use App\Http\Middleware\Universal\EnsureKnownDomain;
use App\Support\RouteLoader;
use Illuminate\Support\Facades\Route;

Route::middleware([PreventAccessFromTenantDomains::class])->group(function () {
    RouteLoader::load('central');
});

Route::middleware([EnsureKnownDomain::class])->group(function () {
    RouteLoader::load('universal');
});
