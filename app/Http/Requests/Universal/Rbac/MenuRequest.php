<?php

declare(strict_types=1);

namespace App\Http\Requests\Universal\Rbac;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
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
            'parent_id' => ['nullable', 'string', Rule::exists('rbac_menus', 'id')],
            'sort_num' => ['required', 'integer', 'min:0'],
            'icon' => ['nullable', 'string', 'max:100'],
            'label_name' => ['required', 'string', 'max:120'],
            'controller_name' => ['nullable', 'string', 'max:255'],
            'route_name' => ['nullable', 'string', 'max:150'],
            'url' => ['nullable', 'string', 'max:255'],
            'permission_prefix' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/'],
            'is_active' => ['required', 'boolean'],
            'is_divider' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label_name.required' => 'Nama label menu wajib diisi.',
            'permission_prefix.regex' => 'Prefix izin hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.',
        ];
    }
}
