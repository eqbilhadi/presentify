<?php

declare(strict_types=1);

namespace App\Http\Requests\Universal\Rbac;

use App\Models\Menu;
use App\Models\MenuAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuActionRequest extends FormRequest
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
        $menuRoute = $this->route('menu');
        $menuActionRoute = $this->route('menuAction');

        $menuId = $menuRoute instanceof Menu
            ? $menuRoute->id
            : ($menuActionRoute instanceof MenuAction ? $menuActionRoute->menu_id : null);

        $menuActionId = $menuActionRoute instanceof MenuAction ? $menuActionRoute->id : null;

        return [
            'action' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('rbac_menu_actions', 'action')
                    ->where('menu_id', $menuId)
                    ->ignore($menuActionId),
            ],
            'label' => ['required', 'string', 'max:120'],
            'route_name' => ['nullable', 'string', 'max:150'],
            'order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Nama aksi wajib diisi.',
            'action.regex' => 'Nama aksi hanya boleh berisi huruf kecil, angka, dan garis bawah.',
            'action.unique' => 'Aksi ini sudah terdaftar pada menu yang dipilih.',
            'label.required' => 'Label tampilan aksi wajib diisi.',
        ];
    }
}
