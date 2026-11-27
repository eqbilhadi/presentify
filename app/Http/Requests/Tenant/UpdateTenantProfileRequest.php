<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tenant_profile.update') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'contact_email' => ['nullable', 'string', 'email:rfc', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama instansi wajib diisi.',
            'name.min' => 'Nama instansi minimal 2 karakter.',
            'name.max' => 'Nama instansi maksimal 150 karakter.',
            'contact_email.email' => 'Format email kontak instansi tidak valid.',
            'contact_email.max' => 'Email kontak maksimal 150 karakter.',
            'phone.max' => 'Nomor telepon maksimal 40 karakter.',
            'address.max' => 'Alamat instansi maksimal 500 karakter.',
            'website.url' => 'URL situs web harus diawali protokol yang valid (contoh: https://instansi.go.id).',
            'website.max' => 'URL situs web maksimal 255 karakter.',
            'tagline.max' => 'Slogan instansi maksimal 200 karakter.',
        ];
    }
}
