<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SsoCallbackRequest;
use App\Models\Tenant;
use App\Services\Auth\SsoService;
use Illuminate\Http\RedirectResponse;

class SsoCallbackController extends Controller
{
    public function __invoke(SsoCallbackRequest $request, SsoService $service): RedirectResponse
    {
        $tenant = tenancy()->tenant;

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        $service->validateAndLogin((string) $request->validated('token'), $tenant, $request);

        return redirect()->to(route('dashboard', absolute: false));
    }
}
