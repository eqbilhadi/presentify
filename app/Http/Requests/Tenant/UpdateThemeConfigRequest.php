<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Services\Tenant\ThemeCustomizerService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        $chartColorValidator = static function (string $attribute, mixed $value, Closure $fail) use ($themeCustomizerService): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value) || ! $themeCustomizerService->isValidChartColor($value)) {
                $fail('Warna grafik harus berupa palet yang tersedia atau kode warna Hex/HSL yang valid.');
            }
        };

        return [
            'primary' => ['required', 'string', 'max:50', $colorValidator],
            'accent' => ['required', 'string', 'max:50', $colorValidator],
            'radius' => ['required', 'string', 'regex:/^(0(\.0+)?|0?\.\d+|1(\.[0-5])?)rem$/'],
            'style' => ['nullable', 'string', Rule::in($themeCustomizerService->allowedStyles())],
            'base_color' => ['nullable', 'string', Rule::in($themeCustomizerService->allowedBaseColors())],
            'theme_color' => ['nullable', 'string', Rule::in($themeCustomizerService->allowedThemeColors())],
            'chart_color' => ['nullable', 'string', 'max:50', $chartColorValidator],
            'heading_font' => ['nullable', 'string', Rule::in($themeCustomizerService->allowedFonts())],
            'body_font' => ['nullable', 'string', Rule::in($themeCustomizerService->allowedFonts())],
            'menu_style' => ['nullable', 'string', Rule::in($themeCustomizerService->allowedMenuStyles())],
            'menu_accent' => ['nullable', 'string', Rule::in($themeCustomizerService->allowedMenuAccents())],
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
            'style.in' => 'Style tampilan yang dipilih tidak valid.',
            'base_color.in' => 'Palet warna dasar (Base Color) tidak valid.',
            'theme_color.in' => 'Pilihan tema warna tidak valid.',
            'heading_font.in' => 'Font judul yang dipilih tidak tersedia.',
            'body_font.in' => 'Font isi (body) yang dipilih tidak tersedia.',
            'menu_style.in' => 'Gaya menu sidebar tidak valid.',
            'menu_accent.in' => 'Gaya aksen menu sidebar tidak valid.',
        ];
    }
}
