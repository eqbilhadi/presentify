<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;
use InvalidArgumentException;

class ThemeCustomizerService
{
    private const HEX_PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    private const HSL_PATTERN = '/^hsl\(\s*(\d{1,3}(?:\.\d+)?)\s*(?:,|\s)\s*(\d{1,3}(?:\.\d+)?)%\s*(?:,|\s)\s*(\d{1,3}(?:\.\d+)?)%\s*\)$/i';

    public const MANAGED_VARIABLES = [
        '--primary',
        '--primary-foreground',
        '--accent',
        '--accent-foreground',
        '--sidebar-primary',
        '--sidebar-primary-foreground',
        '--sidebar-accent',
        '--sidebar-accent-foreground',
        '--ring',
        '--radius',
    ];

    /**
     * @var array<int, array{key: string, label: string, description: string, primary: string, accent: string, radius: string}>
     */
    private const PRESETS = [
        [
            'key' => 'samudra',
            'label' => 'Samudra Biru',
            'description' => 'Nuansa birokrasi formal dengan aksen biru bersih.',
            'primary' => '#1d4ed8',
            'accent' => '#eff6ff',
            'radius' => '0.5rem',
        ],
        [
            'key' => 'zamrud',
            'label' => 'Zamrud Layanan',
            'description' => 'Hijau tua tenang untuk portal layanan publik dan klinik.',
            'primary' => '#0f766e',
            'accent' => '#f0fdfa',
            'radius' => '0.625rem',
        ],
        [
            'key' => 'batu',
            'label' => 'Batu Slate',
            'description' => 'Monokrom tegas dengan sudut ringkas dan kontras tinggi.',
            'primary' => '#1e293b',
            'accent' => '#f1f5f9',
            'radius' => '0.375rem',
        ],
        [
            'key' => 'terakota',
            'label' => 'Terakota Hangat',
            'description' => 'Karakter hangat untuk institusi pendidikan dan komunitas.',
            'primary' => '#b45309',
            'accent' => '#fffbeb',
            'radius' => '0.625rem',
        ],
        [
            'key' => 'nila',
            'label' => 'Nila Klasik',
            'description' => 'Aksen indigo terstruktur untuk perusahaan dan kampus.',
            'primary' => '#4338ca',
            'accent' => '#eef2ff',
            'radius' => '0.5rem',
        ],
    ];

    /**
     * @var array<int, array{value: string, label: string}>
     */
    private const RADIUS_OPTIONS = [
        ['value' => '0rem', 'label' => 'Tajam (0rem)'],
        ['value' => '0.25rem', 'label' => 'Ringkas (0.25rem)'],
        ['value' => '0.375rem', 'label' => 'Sedang (0.375rem)'],
        ['value' => '0.5rem', 'label' => 'Standar (0.5rem)'],
        ['value' => '0.625rem', 'label' => 'Bawaan (0.625rem)'],
        ['value' => '0.75rem', 'label' => 'Lembut (0.75rem)'],
        ['value' => '1rem', 'label' => 'Bulat (1rem)'],
    ];

    public function isValidColor(string $value): bool
    {
        $trimmed = trim($value);

        if (preg_match(self::HEX_PATTERN, $trimmed) === 1) {
            return true;
        }

        if (preg_match(self::HSL_PATTERN, $trimmed, $matches) === 1) {
            $hue = (float) $matches[1];
            $saturation = (float) $matches[2];
            $lightness = (float) $matches[3];

            return $hue >= 0.0 && $hue <= 360.0
                && $saturation >= 0.0 && $saturation <= 100.0
                && $lightness >= 0.0 && $lightness <= 100.0;
        }

        return false;
    }

    public function normalizeColor(string $value): string
    {
        $trimmed = trim($value);

        if (preg_match(self::HEX_PATTERN, $trimmed) === 1) {
            $hex = strtolower($trimmed);

            if (strlen($hex) === 4) {
                return sprintf('#%s%s%s%s%s%s', $hex[1], $hex[1], $hex[2], $hex[2], $hex[3], $hex[3]);
            }

            return $hex;
        }

        if (preg_match(self::HSL_PATTERN, $trimmed, $matches) === 1) {
            $hue = round((float) $matches[1], 1);
            $saturation = round((float) $matches[2], 1);
            $lightness = round((float) $matches[3], 1);

            if ($hue < 0.0 || $hue > 360.0 || $saturation < 0.0 || $saturation > 100.0 || $lightness < 0.0 || $lightness > 100.0) {
                throw new InvalidArgumentException("HSL color out of bounds: {$value}");
            }

            return sprintf(
                'hsl(%s %s%% %s%%)',
                $this->formatNumeric($hue),
                $this->formatNumeric($saturation),
                $this->formatNumeric($lightness),
            );
        }

        throw new InvalidArgumentException("Unsupported color format: {$value}");
    }

    /**
     * @param  array<string, string>|null  $themeConfig
     * @return array<string, string>
     */
    public function formatCssVariables(?array $themeConfig, bool $dark = false): array
    {
        if (! is_array($themeConfig) || $themeConfig === []) {
            return [];
        }

        $variables = [];

        $primary = $this->extractValidColor($themeConfig['primary'] ?? null);
        if ($primary !== null) {
            $resolvedPrimary = $dark ? $this->adaptColorForDarkSurface($primary, 0.25) : $primary;
            $primaryForeground = $this->pickContrastingForeground($resolvedPrimary);

            $variables['--primary'] = $resolvedPrimary;
            $variables['--primary-foreground'] = $primaryForeground;
            $variables['--sidebar-primary'] = $resolvedPrimary;
            $variables['--sidebar-primary-foreground'] = $primaryForeground;
            $variables['--ring'] = $resolvedPrimary;
        }

        $accent = $this->extractValidColor($themeConfig['accent'] ?? null);
        if ($accent !== null) {
            $resolvedAccent = $dark ? $this->adaptAccentForDarkSurface($accent) : $accent;
            $accentForeground = $this->pickContrastingForeground($resolvedAccent);

            $variables['--accent'] = $resolvedAccent;
            $variables['--accent-foreground'] = $accentForeground;
            $variables['--sidebar-accent'] = $resolvedAccent;
            $variables['--sidebar-accent-foreground'] = $accentForeground;
        }

        $radius = $themeConfig['radius'] ?? null;
        if (is_string($radius) && trim($radius) !== '') {
            $variables['--radius'] = trim($radius);
        }

        return $variables;
    }

    /**
     * @return array{
     *     config: array{primary: string|null, accent: string|null, radius: string|null}|null,
     *     css_variables: array<string, string>,
     *     dark_css_variables: array<string, string>,
     * }
     */
    public function resolveThemePayload(Tenant $tenant): array
    {
        $rawConfig = $tenant->theme_config;

        if (! is_array($rawConfig) || $rawConfig === []) {
            return [
                'config' => null,
                'css_variables' => [],
                'dark_css_variables' => [],
            ];
        }

        $primary = $this->extractValidColor($rawConfig['primary'] ?? null);
        $accent = $this->extractValidColor($rawConfig['accent'] ?? null);
        $radius = isset($rawConfig['radius']) && trim($rawConfig['radius']) !== ''
            ? trim($rawConfig['radius'])
            : null;

        $normalized = array_filter([
            'primary' => $primary,
            'accent' => $accent,
            'radius' => $radius,
        ], static fn (?string $value): bool => $value !== null);

        if ($normalized === []) {
            return [
                'config' => null,
                'css_variables' => [],
                'dark_css_variables' => [],
            ];
        }

        return [
            'config' => [
                'primary' => $primary,
                'accent' => $accent,
                'radius' => $radius,
            ],
            'css_variables' => $this->formatCssVariables($normalized, false),
            'dark_css_variables' => $this->formatCssVariables($normalized, true),
        ];
    }

    /**
     * @return array{
     *     config: array{primary: string|null, accent: string|null, radius: string|null}|null,
     *     css_variables: array<string, string>,
     *     dark_css_variables: array<string, string>,
     *     presets: array<int, array{key: string, label: string, description: string, primary: string, accent: string, radius: string}>,
     *     radius_options: array<int, array{value: string, label: string}>,
     *     defaults: array{primary: string, accent: string, radius: string},
     * }
     */
    public function getEditorData(Tenant $tenant): array
    {
        return [
            ...$this->resolveThemePayload($tenant),
            'presets' => self::PRESETS,
            'radius_options' => self::RADIUS_OPTIONS,
            'defaults' => [
                'primary' => '#18181b',
                'accent' => '#f4f4f5',
                'radius' => '0.625rem',
            ],
        ];
    }

    /**
     * @param  array{primary: string, accent: string, radius: string}  $data
     */
    public function updateTheme(Tenant $tenant, array $data): Tenant
    {
        $tenant->theme_config = [
            'primary' => $this->normalizeColor($data['primary']),
            'accent' => $this->normalizeColor($data['accent']),
            'radius' => trim($data['radius']),
        ];
        $tenant->save();

        return $tenant;
    }

    public function resetTheme(Tenant $tenant): Tenant
    {
        $tenant->theme_config = null;
        $tenant->save();

        return $tenant;
    }

    private function extractValidColor(mixed $value): ?string
    {
        if (! is_string($value) || ! $this->isValidColor($value)) {
            return null;
        }

        return $this->normalizeColor($value);
    }

    private function pickContrastingForeground(string $color): string
    {
        [$r, $g, $b] = $this->toRgb($color);
        $luminance = $this->relativeLuminance($r, $g, $b);

        return $luminance > 0.179 ? '#09090b' : '#ffffff';
    }

    private function adaptColorForDarkSurface(string $color, float $minLuminance): string
    {
        [$r, $g, $b] = $this->toRgb($color);

        if ($this->relativeLuminance($r, $g, $b) >= $minLuminance) {
            return $color;
        }

        [$h, $s, $l] = $this->rgbToHsl($r, $g, $b);
        $adjustedLightness = max($l, 65.0);

        return $this->hslToHex($h, $s, $adjustedLightness);
    }

    private function adaptAccentForDarkSurface(string $color): string
    {
        [$r, $g, $b] = $this->toRgb($color);

        if ($this->relativeLuminance($r, $g, $b) <= 0.28) {
            return $color;
        }

        [$h, $s] = $this->rgbToHsl($r, $g, $b);

        return $this->hslToHex($h, min($s, 36.0), 22.0);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function toRgb(string $normalizedColor): array
    {
        if (str_starts_with($normalizedColor, '#')) {
            return [
                (int) hexdec(substr($normalizedColor, 1, 2)),
                (int) hexdec(substr($normalizedColor, 3, 2)),
                (int) hexdec(substr($normalizedColor, 5, 2)),
            ];
        }

        preg_match(self::HSL_PATTERN, $normalizedColor, $matches);
        $h = ((float) ($matches[1] ?? 0.0)) / 360.0;
        $s = ((float) ($matches[2] ?? 0.0)) / 100.0;
        $l = ((float) ($matches[3] ?? 0.0)) / 100.0;

        if ($s === 0.0) {
            $gray = (int) round($l * 255);

            return [$gray, $gray, $gray];
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        return [
            (int) round($this->hueToChannel($p, $q, $h + 1 / 3) * 255),
            (int) round($this->hueToChannel($p, $q, $h) * 255),
            (int) round($this->hueToChannel($p, $q, $h - 1 / 3) * 255),
        ];
    }

    private function hueToChannel(float $p, float $q, float $t): float
    {
        if ($t < 0.0) {
            $t += 1.0;
        }
        if ($t > 1.0) {
            $t -= 1.0;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function rgbToHsl(int $r, int $g, int $b): array
    {
        $rf = $r / 255.0;
        $gf = $g / 255.0;
        $bf = $b / 255.0;

        $max = max($rf, $gf, $bf);
        $min = min($rf, $gf, $bf);
        $l = ($max + $min) / 2.0;

        if ($max === $min) {
            return [0.0, 0.0, $l * 100.0];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2.0 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $rf => ($gf - $bf) / $d + ($gf < $bf ? 6.0 : 0.0),
            $gf => ($bf - $rf) / $d + 2.0,
            default => ($rf - $gf) / $d + 4.0,
        };

        return [($h / 6.0) * 360.0, $s * 100.0, $l * 100.0];
    }

    private function hslToHex(float $h, float $s, float $l): string
    {
        [$r, $g, $b] = $this->toRgb(sprintf(
            'hsl(%s %s%% %s%%)',
            $this->formatNumeric($h),
            $this->formatNumeric($s),
            $this->formatNumeric($l),
        ));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function relativeLuminance(int $r, int $g, int $b): float
    {
        $channels = array_map(static function (int $channel): float {
            $srgb = $channel / 255.0;

            return $srgb <= 0.03928
                ? $srgb / 12.92
                : (($srgb + 0.055) / 1.055) ** 2.4;
        }, [$r, $g, $b]);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private function formatNumeric(float $number): string
    {
        $formatted = number_format($number, 1, '.', '');

        return str_ends_with($formatted, '.0')
            ? substr($formatted, 0, -2)
            : $formatted;
    }
}
