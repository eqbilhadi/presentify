<?php

declare(strict_types=1);

namespace App\Http\Requests\Rbac;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
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
            'label' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['required', 'array'],
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
            'permission_ids.required' => 'Daftar izin wajib dikirimkan.',
            'permission_ids.*.exists' => 'Salah satu izin yang dipilih tidak ditemukan.',
        ];
    }

    /**
     * @return array{label?: string, description: string|null, permission_ids: array<int, int>}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        $payload = [
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'permission_ids' => array_map('intval', is_array($validated['permission_ids'] ?? null) ? $validated['permission_ids'] : []),
        ];

        if ($this->has('label')) {
            $payload['label'] = $this->string('label')->toString();
        }

        return $payload;
    }
}
