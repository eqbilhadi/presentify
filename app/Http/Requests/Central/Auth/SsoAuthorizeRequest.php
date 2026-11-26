<?php

namespace App\Http\Requests\Central\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SsoAuthorizeRequest extends FormRequest
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
        return [
            'domain' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:40'],
        ];
    }
}
