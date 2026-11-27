<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TenantProfileService
{
    private const LOGO_MAX_DIMENSION = 512;

    private const FAVICON_MAX_DIMENSION = 128;

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     slug: string,
     *     contact_email: string|null,
     *     phone: string|null,
     *     address: string|null,
     *     website: string|null,
     *     tagline: string|null,
     *     logo_url: string|null,
     *     favicon_url: string|null,
     *     is_active: bool,
     *     domains: array<int, string>,
     * }
     */
    public function getProfile(Tenant $tenant): array
    {
        $tenant->loadMissing('domains');

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'contact_email' => $tenant->contact_email,
            'phone' => is_string($tenant->phone) ? $tenant->phone : null,
            'address' => is_string($tenant->address) ? $tenant->address : null,
            'website' => is_string($tenant->website) ? $tenant->website : null,
            'tagline' => is_string($tenant->tagline) ? $tenant->tagline : null,
            'logo_url' => $tenant->logoUrl(),
            'favicon_url' => $tenant->faviconUrl(),
            'is_active' => (bool) $tenant->is_active,
            'domains' => $tenant->domains
                ->pluck('domain')
                ->filter(static fn (mixed $domain): bool => is_string($domain) && $domain !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{
     *     name: string,
     *     contact_email?: string|null,
     *     phone?: string|null,
     *     address?: string|null,
     *     website?: string|null,
     *     tagline?: string|null,
     * }  $data
     */
    public function updateProfile(Tenant $tenant, array $data): Tenant
    {
        $tenant->name = trim($data['name']);
        $tenant->contact_email = $this->normalizeNullableString($data['contact_email'] ?? null);
        $tenant->phone = $this->normalizeNullableString($data['phone'] ?? null);
        $tenant->address = $this->normalizeNullableString($data['address'] ?? null);
        $tenant->website = $this->normalizeNullableString($data['website'] ?? null);
        $tenant->tagline = $this->normalizeNullableString($data['tagline'] ?? null);
        $tenant->save();

        return $tenant;
    }

    public function uploadBrandingImage(Tenant $tenant, UploadedFile $file, string $type): Tenant
    {
        [$attribute, $maxDimension] = $this->resolveBrandingSpec($type);

        $disk = Storage::disk('public');
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'png'));

        if ($type === 'favicon' && $extension === 'ico') {
            $contents = file_get_contents($file->getRealPath());

            if ($contents === false || $contents === '') {
                throw ValidationException::withMessages([
                    'file' => 'Berkas favicon tidak dapat dibaca.',
                ]);
            }

            $storedExtension = 'ico';
        } else {
            $contents = $this->resizeToPng($file, $maxDimension);
            $storedExtension = 'png';
        }

        $newPath = sprintf('branding/%s-%s.%s', $type, strtolower((string) Str::ulid()), $storedExtension);
        $disk->put($newPath, $contents);

        $previousPath = $tenant->{$attribute};

        $tenant->{$attribute} = $newPath;
        $tenant->save();

        if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $newPath) {
            $disk->delete($previousPath);
        }

        return $tenant;
    }

    public function removeBrandingImage(Tenant $tenant, string $type): Tenant
    {
        [$attribute] = $this->resolveBrandingSpec($type);
        $previousPath = $tenant->{$attribute};

        if (is_string($previousPath) && $previousPath !== '') {
            Storage::disk('public')->delete($previousPath);
        }

        $tenant->{$attribute} = null;
        $tenant->save();

        return $tenant;
    }

    public function getAssetFilePath(string $path): string
    {
        $normalized = ltrim($path, '/');

        if ($normalized === '' || str_contains($normalized, '..') || str_contains($normalized, '\\')) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($normalized)) {
            abort(404);
        }

        $rootPath = realpath($disk->path(''));
        $filePath = realpath($disk->path($normalized));

        if ($rootPath === false || $filePath === false || ! str_starts_with($filePath, $rootPath)) {
            abort(404);
        }

        return $filePath;
    }

    /**
     * @return array{0: 'logo_path'|'favicon_path', 1: int}
     */
    private function resolveBrandingSpec(string $type): array
    {
        return match ($type) {
            'logo' => ['logo_path', self::LOGO_MAX_DIMENSION],
            'favicon' => ['favicon_path', self::FAVICON_MAX_DIMENSION],
            default => throw new InvalidArgumentException("Unsupported branding type: {$type}"),
        };
    }

    private function resizeToPng(UploadedFile $file, int $maxDimension): string
    {
        $realPath = $file->getRealPath();

        if ($realPath === false) {
            throw ValidationException::withMessages([
                'file' => 'Berkas gambar gagal diproses.',
            ]);
        }

        $rawBinary = file_get_contents($realPath);

        if ($rawBinary === false) {
            throw ValidationException::withMessages([
                'file' => 'Berkas gambar tidak dapat dibaca.',
            ]);
        }

        $sourceImage = @imagecreatefromstring($rawBinary);

        if ($sourceImage === false) {
            throw ValidationException::withMessages([
                'file' => 'Berkas gambar rusak atau bukan format gambar raster yang valid.',
            ]);
        }

        try {
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);

            $scale = min(1.0, $maxDimension / max($sourceWidth, $sourceHeight));
            $targetWidth = max(1, (int) round($sourceWidth * $scale));
            $targetHeight = max(1, (int) round($sourceHeight * $scale));

            $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($targetImage === false) {
                throw ValidationException::withMessages([
                    'file' => 'Gagal menyiapkan kanvas gambar.',
                ]);
            }

            try {
                imagealphablending($targetImage, false);
                imagesavealpha($targetImage, true);
                $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);

                if ($transparent !== false) {
                    imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
                }

                imagecopyresampled(
                    $targetImage,
                    $sourceImage,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $sourceWidth,
                    $sourceHeight,
                );

                ob_start();
                imagepng($targetImage, null, 6);
                $encoded = ob_get_clean();

                if (! is_string($encoded) || $encoded === '') {
                    throw ValidationException::withMessages([
                        'file' => 'Gagal mengonversi gambar branding.',
                    ]);
                }

                return $encoded;
            } finally {
                imagedestroy($targetImage);
            }
        } finally {
            imagedestroy($sourceImage);
        }
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
