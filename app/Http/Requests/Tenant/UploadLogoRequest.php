<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tenant_profile.update') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        $fileRules = $type === 'favicon'
            ? ['required', 'file', 'mimes:png,jpg,jpeg,webp,ico', 'max:1024']
            : ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];

        return [
            'type' => ['required', 'string', Rule::in(['logo', 'favicon'])],
            'file' => $fileRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Jenis aset branding wajib ditentukan.',
            'type.in' => 'Jenis aset branding hanya mendukung logo atau favicon.',
            'file.required' => 'Berkas gambar wajib dipilih sebelum mengunggah.',
            'file.image' => 'Logo instansi harus berupa berkas gambar yang valid.',
            'file.mimes' => 'Format berkas tidak didukung. Gunakan PNG, JPG, WebP (atau ICO khusus favicon).',
            'file.max' => 'Ukuran berkas melebihi batas maksimum yang diizinkan.',
        ];
    }
}
