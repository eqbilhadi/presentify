<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SwitchTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        // Only allow switching to tenants the authenticated user actively belongs to.
        $allowedTenantIds = $user->activeTenants()
            ->where('sys_tenants.is_active', true)
            ->pluck('sys_tenants.id')
            ->toArray();

        return [
            'tenant_id' => ['required', 'string', 'in:'.implode(',', $allowedTenantIds)],
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
