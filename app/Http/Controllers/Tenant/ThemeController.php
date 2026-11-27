<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateThemeConfigRequest;
use App\Models\Tenant;
use App\Services\Tenant\ThemeCustomizerService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    public function __construct(
        private readonly ThemeCustomizerService $themeCustomizerService,
    ) {}

    public function edit(): Response
    {
        $tenant = $this->currentTenant();

        return Inertia::render('tenant/theme/edit', [
            'theme' => $this->themeCustomizerService->getEditorData($tenant),
        ]);
    }

    public function update(UpdateThemeConfigRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();

        /** @var array{primary: string, accent: string, radius: string} $payload */
        $payload = $request->validated();
        $this->themeCustomizerService->updateTheme($tenant, $payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tema warna dan tampilan instansi berhasil diperbarui.',
        ]);

        return to_route('tenant.theme.edit');
    }

    public function reset(): RedirectResponse
    {
        $tenant = $this->currentTenant();
        $this->themeCustomizerService->resetTheme($tenant);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tema instansi dikembalikan ke pengaturan bawaan.',
        ]);

        return to_route('tenant.theme.edit');
    }

    private function currentTenant(): Tenant
    {
        $tenant = tenancy()->tenant;
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
