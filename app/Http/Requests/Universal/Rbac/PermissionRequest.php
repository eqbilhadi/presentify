<?php

declare(strict_types=1);

namespace App\Http\Requests\Universal\Rbac;

use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionRequest extends FormRequest
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
        $permission = $this->route('permission');
        $permissionId = $permission instanceof Permission ? $permission->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('rbac_permissions', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($permissionId),
            ],
            'group' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama izin wajib diisi.',
            'name.regex' => 'Nama izin hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.',
            'name.unique' => 'Nama izin sudah digunakan.',
        ];
    }
}
