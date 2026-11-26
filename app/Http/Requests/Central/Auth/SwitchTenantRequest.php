<?php

namespace App\Http\Requests\Central\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchTenantRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->user();

        $allowedTenantIds = $user->activeTenants()
            ->where('sys_tenants.is_active', true)
            ->pluck('sys_tenants.id')
            ->all();

        return [
            'tenant_id' => ['required', 'string', Rule::in($allowedTenantIds)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenant_id.in' => 'Instansi tidak ditemukan atau Anda tidak memiliki akses.',
        ];
    }
}
