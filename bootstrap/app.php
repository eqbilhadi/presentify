<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InitializeTenancyBySubdomainIfApplicable;
use App\Http\Middleware\PreventAccessFromTenantDomains;
use App\Http\Middleware\Universal\EnsureKnownDomain;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Exceptions\NotASubdomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Ensure tenancy initializes after StartSession and domain guards run before auth and route bindings.
        $middleware->appendToPriorityList(
            StartSession::class,
            InitializeTenancyBySubdomainIfApplicable::class,
        );
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            EnsureKnownDomain::class,
        );
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            PreventAccessFromTenantDomains::class,
        );

        $middleware->redirectGuestsTo(function (Request $request): string {
            /** @var array<int, string> $centralDomains */
            $centralDomains = config('tenancy.central_domains', []);

            if (! in_array($request->getHost(), $centralDomains, true) && Route::has('tenant.sso.login')) {
                return route('tenant.sso.login');
            }

            return route('login');
        });

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            InitializeTenancyBySubdomainIfApplicable::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TenantCouldNotBeIdentifiedException|NotASubdomainException $e) {
            abort(404);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
