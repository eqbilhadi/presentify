<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantProfileRequest;
use App\Http\Requests\Tenant\UploadLogoRequest;
use App\Models\Tenant;
use App\Services\Tenant\TenantProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TenantProfileController extends Controller
{
    public function __construct(
        private readonly TenantProfileService $tenantProfileService,
    ) {}

    public function edit(): Response
    {
        $tenant = $this->currentTenant();

        return Inertia::render('tenant/profile/edit', [
            'profile' => $this->tenantProfileService->getProfile($tenant),
        ]);
    }

    public function update(UpdateTenantProfileRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();

        /** @var array{name: string, contact_email?: string|null, phone?: string|null, address?: string|null, website?: string|null, tagline?: string|null} $payload */
        $payload = $request->validated();
        $this->tenantProfileService->updateProfile($tenant, $payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Profil instansi berhasil diperbarui.',
        ]);

        return to_route('tenant.profile.edit');
    }

    public function uploadLogo(UploadLogoRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();

        /** @var UploadedFile $file */
        $file = $request->file('file');

        /** @var string $type */
        $type = $request->validated('type');

        $this->tenantProfileService->uploadBrandingImage($tenant, $file, $type);

        $label = $type === 'favicon' ? 'Favicon instansi' : 'Logo instansi';

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$label} berhasil diperbarui.",
        ]);

        return to_route('tenant.profile.edit');
    }

    public function destroyLogo(string $type): RedirectResponse
    {
        $tenant = $this->currentTenant();
        $this->tenantProfileService->removeBrandingImage($tenant, $type);

        $label = $type === 'favicon' ? 'Favicon instansi' : 'Logo instansi';

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$label} berhasil dihapus.",
        ]);

        return to_route('tenant.profile.edit');
    }

    public function asset(string $path): BinaryFileResponse
    {
        $filePath = $this->tenantProfileService->getAssetFilePath($path);

        return response()->file($filePath, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function currentTenant(): Tenant
    {
        $tenant = tenancy()->tenant;
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
