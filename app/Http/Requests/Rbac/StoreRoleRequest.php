<?php

declare(strict_types=1);

namespace App\Http\Requests\Rbac;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', Rule::unique('rbac_roles', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('rbac_permissions', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Nama peran wajib diisi.',
            'label.max' => 'Nama peran maksimal 100 karakter.',
            'name.regex' => 'Kode peran hanya boleh berisi huruf kecil, angka, dan garis bawah (_).',
            'name.unique' => 'Kode peran sudah digunakan pada instansi ini.',
            'permission_ids.*.exists' => 'Salah satu izin yang dipilih tidak ditemukan.',
        ];
    }

    /**
     * @return array{label: string, name: string|null, description: string|null, permission_ids: array<int, int>}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'label' => $this->string('label')->toString(),
            'name' => $this->filled('name') ? $this->string('name')->toString() : null,
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'permission_ids' => array_map('intval', is_array($validated['permission_ids'] ?? null) ? $validated['permission_ids'] : []),
        ];
    }
}
