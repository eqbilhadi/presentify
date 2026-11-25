<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Rbac\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces 2-tier tenant permissions on protected routes.
 * Supports pipe-separated OR syntax: `permission:settings.roles.view|manage_tenant`.
 */
class EnsureHasPermission
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! tenancy()->initialized) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        $allowedNames = [];
        foreach ($permissions as $group) {
            foreach (explode('|', $group) as $name) {
                $trimmed = trim($name);
                if ($trimmed !== '') {
                    $allowedNames[] = $trimmed;
                }
            }
        }

        if ($allowedNames === [] || ! $this->permissionService->allowsAny($user, $allowedNames)) {
            abort(403, 'Anda tidak memiliki izin untuk melakukan tindakan ini.');
        }

        return $next($request);
    }
}
