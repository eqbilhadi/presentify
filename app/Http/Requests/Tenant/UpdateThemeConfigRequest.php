<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Services\Tenant\ThemeCustomizerService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tenant_theme.update') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(ThemeCustomizerService $themeCustomizerService): array
    {
        $colorValidator = static function (string $attribute, mixed $value, Closure $fail) use ($themeCustomizerService): void {
            if (! is_string($value) || ! $themeCustomizerService->isValidColor($value)) {
                $label = $attribute === 'primary' ? 'Warna utama' : 'Warna aksen';
                $fail("{$label} harus berupa format Hex (#RRGGBB) atau HSL yang valid (contoh: hsl(173 80% 26%)).");
            }
        };

        return [
            'primary' => ['required', 'string', 'max:50', $colorValidator],
            'accent' => ['required', 'string', 'max:50', $colorValidator],
            'radius' => ['required', 'string', 'regex:/^(0(\.0+)?|0?\.\d+|1(\.[0-5])?)rem$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary.required' => 'Warna utama tema wajib diisi.',
            'accent.required' => 'Warna aksen tema wajib diisi.',
            'radius.required' => 'Radius sudut komponen wajib dipilih.',
            'radius.regex' => 'Radius sudut harus menggunakan satuan rem antara 0rem hingga 1.5rem (contoh: 0.5rem).',
        ];
    }
}
