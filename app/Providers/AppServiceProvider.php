<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Rbac\PermissionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PermissionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRbacGates();
    }

    protected function configureRbacGates(): void
    {
        Gate::define('has-permission', fn (User $user, string $permission): bool => app(PermissionService::class)->allows($user, $permission));

        // Resolve tenant RBAC abilities natively via Gate::allows() / $user->can().
        Gate::before(function (User $user, string $ability): ?bool {
            $permissionService = app(PermissionService::class);

            if (! $permissionService->hasKnownPermission($ability)) {
                return null;
            }

            return $permissionService->allows($user, $ability);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
