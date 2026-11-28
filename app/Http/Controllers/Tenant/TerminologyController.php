<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTerminologyRequest;
use App\Models\Tenant;
use App\Services\Tenant\TerminologyService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TerminologyController extends Controller
{
    public function __construct(
        private readonly TerminologyService $terminologyService,
    ) {}

    public function edit(): Response
    {
        $tenant = $this->currentTenant();

        return Inertia::render('tenant/terminology/edit', [
            'terminology' => $this->terminologyService->getEditorData($tenant),
        ]);
    }

    public function update(UpdateTerminologyRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();

        /** @var array<string, string> $terms */
        $terms = $request->validated('terms');
        $this->terminologyService->updateTerminology($tenant, $terms);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Kamus terminologi instansi berhasil diperbarui.',
        ]);

        return to_route('tenant.terminology.edit');
    }

    public function reset(): RedirectResponse
    {
        $tenant = $this->currentTenant();
        $this->terminologyService->resetTerminology($tenant);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Kamus terminologi dikembalikan ke istilah bawaan.',
        ]);

        return to_route('tenant.terminology.edit');
    }

    private function currentTenant(): Tenant
    {
        $tenant = tenancy()->tenant;
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
