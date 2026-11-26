<?php

declare(strict_types=1);

namespace App\Http\Requests\Universal\Rbac;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
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
        $role = $this->route('role');
        $roleId = $role instanceof Role ? $role->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('rbac_roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($roleId),
            ],
            'menu_ids' => ['nullable', 'array'],
            'menu_ids.*' => ['string', Rule::exists('rbac_menus', 'id')],
            'menu_action_ids' => ['nullable', 'array'],
            'menu_action_ids.*' => ['string', Rule::exists('rbac_menu_actions', 'id')],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['string', Rule::exists('rbac_permissions', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama peran wajib diisi.',
            'name.regex' => 'Nama peran hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.',
            'name.unique' => 'Nama peran sudah digunakan.',
        ];
    }
}
