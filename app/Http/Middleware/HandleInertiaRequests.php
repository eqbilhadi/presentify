<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\WorkspaceService;
use App\Services\Rbac\MenuService;
use App\Services\Tenant\ThemeCustomizerService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly MenuService $menuService,
        private readonly ThemeCustomizerService $themeCustomizerService,
    ) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeTenant = tenancy()->initialized ? tenant() : null;

        $currentTenant = $activeTenant instanceof Tenant
            ? [
                'id' => $activeTenant->id,
                'name' => $activeTenant->name,
                'slug' => $activeTenant->slug,
                'logo_url' => $activeTenant->logoUrl(),
                'favicon_url' => $activeTenant->faviconUrl(),
                'theme' => $this->themeCustomizerService->resolveThemePayload($activeTenant),
            ]
            : null;

        $availableTenants = $user instanceof User
            ? $this->workspaceService->getActiveTenants($user)
                ->map(fn (Tenant $tenant): array => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ])
                ->values()
                ->all()
            : [];

        $permissions = $user instanceof User
            ? $user->getAllPermissions()->pluck('name')->values()->all()
            : [];

        $menus = $user instanceof User
            ? $this->menuService->getMenusForUser($user)
            : [];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => $permissions,
                'menus' => $menus,
            ],
            'tenancy' => [
                'current' => $currentTenant,
                'tenants' => $availableTenants,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
