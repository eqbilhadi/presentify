<?php

declare(strict_types=1);

namespace App\Http\Requests\Universal\Rbac;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuReorderRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'string', 'distinct', Rule::exists('rbac_menus', 'id')],
            'items.*.parent_id' => ['nullable', 'string', Rule::exists('rbac_menus', 'id')],
            'items.*.sort_num' => ['required', 'integer', 'min:0'],
        ];
    }
}
