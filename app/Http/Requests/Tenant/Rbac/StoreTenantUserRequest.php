<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Rbac;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $email = strtolower(trim((string) $this->input('email', '')));
        $existingUser = $email !== '' && User::query()->where('email', $email)->exists();

        return [
            'name' => [$existingUser ? 'nullable' : 'required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => [$existingUser ? 'nullable' : 'required', 'string', Password::defaults(), 'confirmed'],
            'nip' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['string', Rule::exists('rbac_roles', 'id')->where('guard_name', 'web')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi untuk akun baru.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi karena email belum terdaftar di sistem.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'role_ids.required' => 'Pilih minimal satu peran di instansi ini.',
            'role_ids.min' => 'Pilih minimal satu peran di instansi ini.',
        ];
    }
}
