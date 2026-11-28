<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;
use InvalidArgumentException;

class ThemeCustomizerService
{
    private const HEX_PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    private const HSL_PATTERN = '/^hsl\(\s*(\d{1,3}(?:\.\d+)?)\s*(?:,|\s)\s*(\d{1,3}(?:\.\d+)?)%\s*(?:,|\s)\s*(\d{1,3}(?:\.\d+)?)%\s*\)$/i';

    private const PRESET_PREFIX = 'prs_';

    public const DEFAULT_STYLE = 'vega';

    public const DEFAULT_BASE_COLOR = 'neutral';

    public const DEFAULT_THEME_COLOR = 'neutral';

    public const DEFAULT_CHART_COLOR = 'orange';

    public const DEFAULT_HEADING_FONT = 'Instrument Sans';

    public const DEFAULT_BODY_FONT = 'Instrument Sans';

    public const DEFAULT_RADIUS = '0.625rem';

    public const DEFAULT_MENU_STYLE = 'inset';

    public const DEFAULT_MENU_ACCENT = 'subtle';

    public const MANAGED_VARIABLES = [
        '--background',
        '--foreground',
        '--card',
        '--card-foreground',
        '--popover',
        '--popover-foreground',
        '--primary',
        '--primary-foreground',
        '--secondary',
        '--secondary-foreground',
        '--muted',
        '--muted-foreground',
        '--accent',
        '--accent-foreground',
        '--border',
        '--input',
        '--ring',
        '--radius',
        '--chart-1',
        '--chart-2',
        '--chart-3',
        '--chart-4',
        '--chart-5',
        '--sidebar',
        '--sidebar-foreground',
        '--sidebar-primary',
        '--sidebar-primary-foreground',
        '--sidebar-accent',
        '--sidebar-accent-foreground',
        '--sidebar-border',
        '--sidebar-ring',
        '--font-sans',
        '--font-heading',
    ];

    /**
     * @var array<int, array{key: string, label: string, description: string, default_radius: string}>
     */
    private const STYLE_OPTIONS = [
        [
            'key' => 'vega',
            'label' => 'Vega',
            'description' => 'Standar shadcn/ui modern dengan proporsi seimbang.',
            'default_radius' => '0.5rem',
        ],
        [
            'key' => 'nova',
            'label' => 'Nova',
            'description' => 'Ringkas dan terstruktur untuk operasional padat.',
            'default_radius' => '0.375rem',
        ],
        [
            'key' => 'maia',
            'label' => 'Maia',
            'description' => 'Lembut dan ramah dengan sudut lebih membulat.',
            'default_radius' => '0.75rem',
        ],
        [
            'key' => 'lyra',
            'label' => 'Lyra',
            'description' => 'Tajam, kotak, dan berkarakter editorial teknikal.',
            'default_radius' => '0rem',
        ],
        [
            'key' => 'mira',
            'label' => 'Mira',
            'description' => 'Sangat padat untuk halaman tabel dan analitik.',
            'default_radius' => '0.25rem',
        ],
    ];

    /**
     * @var array<string, array{
     *     key: string,
     *     label: string,
     *     swatch: string,
     *     light: array<string, string>,
     *     dark: array<string, string>
     * }>
     */
    private const BASE_COLOR_OPTIONS = [
        'neutral' => [
            'key' => 'neutral',
            'label' => 'Neutral',
            'swatch' => '#737373',
            'light' => [
                '--background' => 'oklch(1 0 0)',
                '--foreground' => 'oklch(0.145 0 0)',
                '--card' => 'oklch(1 0 0)',
                '--card-foreground' => 'oklch(0.145 0 0)',
                '--popover' => 'oklch(1 0 0)',
                '--popover-foreground' => 'oklch(0.145 0 0)',
                '--secondary' => 'oklch(0.97 0 0)',
                '--secondary-foreground' => 'oklch(0.205 0 0)',
                '--muted' => 'oklch(0.97 0 0)',
                '--muted-foreground' => 'oklch(0.556 0 0)',
                '--border' => 'oklch(0.922 0 0)',
                '--input' => 'oklch(0.922 0 0)',
                '--sidebar' => 'oklch(0.985 0 0)',
                '--sidebar-foreground' => 'oklch(0.145 0 0)',
                '--sidebar-border' => 'oklch(0.922 0 0)',
                '--sidebar-ring' => 'oklch(0.708 0 0)',
            ],
            'dark' => [
                '--background' => 'oklch(0.145 0 0)',
                '--foreground' => 'oklch(0.985 0 0)',
                '--card' => 'oklch(0.205 0 0)',
                '--card-foreground' => 'oklch(0.985 0 0)',
                '--popover' => 'oklch(0.205 0 0)',
                '--popover-foreground' => 'oklch(0.985 0 0)',
                '--secondary' => 'oklch(0.269 0 0)',
                '--secondary-foreground' => 'oklch(0.985 0 0)',
                '--muted' => 'oklch(0.269 0 0)',
                '--muted-foreground' => 'oklch(0.708 0 0)',
                '--border' => 'oklch(1 0 0 / 10%)',
                '--input' => 'oklch(1 0 0 / 15%)',
                '--sidebar' => 'oklch(0.205 0 0)',
                '--sidebar-foreground' => 'oklch(0.985 0 0)',
                '--sidebar-border' => 'oklch(1 0 0 / 10%)',
                '--sidebar-ring' => 'oklch(0.556 0 0)',
            ],
        ],
        'stone' => [
            'key' => 'stone',
            'label' => 'Stone',
            'swatch' => '#78716c',
            'light' => [
                '--background' => 'oklch(1 0 0)',
                '--foreground' => 'oklch(0.147 0.004 49.25)',
                '--card' => 'oklch(1 0 0)',
                '--card-foreground' => 'oklch(0.147 0.004 49.25)',
                '--popover' => 'oklch(1 0 0)',
                '--popover-foreground' => 'oklch(0.147 0.004 49.25)',
                '--secondary' => 'oklch(0.97 0.001 106.424)',
                '--secondary-foreground' => 'oklch(0.216 0.006 56.043)',
                '--muted' => 'oklch(0.97 0.001 106.424)',
                '--muted-foreground' => 'oklch(0.553 0.013 58.071)',
                '--border' => 'oklch(0.923 0.003 48.717)',
                '--input' => 'oklch(0.923 0.003 48.717)',
                '--sidebar' => 'oklch(0.985 0.001 106.423)',
                '--sidebar-foreground' => 'oklch(0.147 0.004 49.25)',
                '--sidebar-border' => 'oklch(0.923 0.003 48.717)',
                '--sidebar-ring' => 'oklch(0.709 0.01 56.259)',
            ],
            'dark' => [
                '--background' => 'oklch(0.147 0.004 49.25)',
                '--foreground' => 'oklch(0.985 0.001 106.423)',
                '--card' => 'oklch(0.216 0.006 56.043)',
                '--card-foreground' => 'oklch(0.985 0.001 106.423)',
                '--popover' => 'oklch(0.216 0.006 56.043)',
                '--popover-foreground' => 'oklch(0.985 0.001 106.423)',
                '--secondary' => 'oklch(0.268 0.007 34.298)',
                '--secondary-foreground' => 'oklch(0.985 0.001 106.423)',
                '--muted' => 'oklch(0.268 0.007 34.298)',
                '--muted-foreground' => 'oklch(0.709 0.01 56.259)',
                '--border' => 'oklch(1 0 0 / 10%)',
                '--input' => 'oklch(1 0 0 / 15%)',
                '--sidebar' => 'oklch(0.216 0.006 56.043)',
                '--sidebar-foreground' => 'oklch(0.985 0.001 106.423)',
                '--sidebar-border' => 'oklch(1 0 0 / 10%)',
                '--sidebar-ring' => 'oklch(0.553 0.013 58.071)',
            ],
        ],
        'zinc' => [
            'key' => 'zinc',
            'label' => 'Zinc',
            'swatch' => '#71717a',
            'light' => [
                '--background' => 'oklch(1 0 0)',
                '--foreground' => 'oklch(0.141 0.005 285.823)',
                '--card' => 'oklch(1 0 0)',
                '--card-foreground' => 'oklch(0.141 0.005 285.823)',
                '--popover' => 'oklch(1 0 0)',
                '--popover-foreground' => 'oklch(0.141 0.005 285.823)',
                '--secondary' => 'oklch(0.967 0.001 286.375)',
                '--secondary-foreground' => 'oklch(0.21 0.006 285.885)',
                '--muted' => 'oklch(0.967 0.001 286.375)',
                '--muted-foreground' => 'oklch(0.552 0.016 285.938)',
                '--border' => 'oklch(0.92 0.004 286.32)',
                '--input' => 'oklch(0.92 0.004 286.32)',
                '--sidebar' => 'oklch(0.985 0 0)',
                '--sidebar-foreground' => 'oklch(0.141 0.005 285.823)',
                '--sidebar-border' => 'oklch(0.92 0.004 286.32)',
                '--sidebar-ring' => 'oklch(0.705 0.015 286.067)',
            ],
            'dark' => [
                '--background' => 'oklch(0.141 0.005 285.823)',
                '--foreground' => 'oklch(0.985 0 0)',
                '--card' => 'oklch(0.21 0.006 285.885)',
                '--card-foreground' => 'oklch(0.985 0 0)',
                '--popover' => 'oklch(0.21 0.006 285.885)',
                '--popover-foreground' => 'oklch(0.985 0 0)',
                '--secondary' => 'oklch(0.274 0.006 286.033)',
                '--secondary-foreground' => 'oklch(0.985 0 0)',
                '--muted' => 'oklch(0.274 0.006 286.033)',
                '--muted-foreground' => 'oklch(0.705 0.015 286.067)',
                '--border' => 'oklch(1 0 0 / 10%)',
                '--input' => 'oklch(1 0 0 / 15%)',
                '--sidebar' => 'oklch(0.21 0.006 285.885)',
                '--sidebar-foreground' => 'oklch(0.985 0 0)',
                '--sidebar-border' => 'oklch(1 0 0 / 10%)',
                '--sidebar-ring' => 'oklch(0.552 0.016 285.938)',
            ],
        ],
        'slate' => [
            'key' => 'slate',
            'label' => 'Slate',
            'swatch' => '#64748b',
            'light' => [
                '--background' => 'oklch(1 0 0)',
                '--foreground' => 'oklch(0.129 0.042 264.695)',
                '--card' => 'oklch(1 0 0)',
                '--card-foreground' => 'oklch(0.129 0.042 264.695)',
                '--popover' => 'oklch(1 0 0)',
                '--popover-foreground' => 'oklch(0.129 0.042 264.695)',
                '--secondary' => 'oklch(0.968 0.007 247.896)',
                '--secondary-foreground' => 'oklch(0.208 0.042 265.755)',
                '--muted' => 'oklch(0.968 0.007 247.896)',
                '--muted-foreground' => 'oklch(0.554 0.046 257.417)',
                '--border' => 'oklch(0.929 0.013 255.508)',
                '--input' => 'oklch(0.929 0.013 255.508)',
                '--sidebar' => 'oklch(0.984 0.003 247.858)',
                '--sidebar-foreground' => 'oklch(0.129 0.042 264.695)',
                '--sidebar-border' => 'oklch(0.929 0.013 255.508)',
                '--sidebar-ring' => 'oklch(0.704 0.04 256.788)',
            ],
            'dark' => [
                '--background' => 'oklch(0.129 0.042 264.695)',
                '--foreground' => 'oklch(0.984 0.003 247.858)',
                '--card' => 'oklch(0.208 0.042 265.755)',
                '--card-foreground' => 'oklch(0.984 0.003 247.858)',
                '--popover' => 'oklch(0.208 0.042 265.755)',
                '--popover-foreground' => 'oklch(0.984 0.003 247.858)',
                '--secondary' => 'oklch(0.279 0.041 260.031)',
                '--secondary-foreground' => 'oklch(0.984 0.003 247.858)',
                '--muted' => 'oklch(0.279 0.041 260.031)',
                '--muted-foreground' => 'oklch(0.704 0.04 256.788)',
                '--border' => 'oklch(1 0 0 / 10%)',
                '--input' => 'oklch(1 0 0 / 15%)',
                '--sidebar' => 'oklch(0.208 0.042 265.755)',
                '--sidebar-foreground' => 'oklch(0.984 0.003 247.858)',
                '--sidebar-border' => 'oklch(1 0 0 / 10%)',
                '--sidebar-ring' => 'oklch(0.551 0.027 264.364)',
            ],
        ],
        'gray' => [
            'key' => 'gray',
            'label' => 'Gray',
            'swatch' => '#6b7280',
            'light' => [
                '--background' => 'oklch(1 0 0)',
                '--foreground' => 'oklch(0.13 0.028 261.692)',
                '--card' => 'oklch(1 0 0)',
                '--card-foreground' => 'oklch(0.13 0.028 261.692)',
                '--popover' => 'oklch(1 0 0)',
                '--popover-foreground' => 'oklch(0.13 0.028 261.692)',
                '--secondary' => 'oklch(0.967 0.003 264.542)',
                '--secondary-foreground' => 'oklch(0.21 0.034 264.665)',
                '--muted' => 'oklch(0.967 0.003 264.542)',
                '--muted-foreground' => 'oklch(0.551 0.027 264.364)',
                '--border' => 'oklch(0.928 0.006 264.531)',
                '--input' => 'oklch(0.928 0.006 264.531)',
                '--sidebar' => 'oklch(0.985 0.002 247.839)',
                '--sidebar-foreground' => 'oklch(0.13 0.028 261.692)',
                '--sidebar-border' => 'oklch(0.928 0.006 264.531)',
                '--sidebar-ring' => 'oklch(0.707 0.022 261.325)',
            ],
            'dark' => [
                '--background' => 'oklch(0.13 0.028 261.692)',
                '--foreground' => 'oklch(0.985 0.002 247.839)',
                '--card' => 'oklch(0.21 0.034 264.665)',
                '--card-foreground' => 'oklch(0.985 0.002 247.839)',
                '--popover' => 'oklch(0.21 0.034 264.665)',
                '--popover-foreground' => 'oklch(0.985 0.002 247.839)',
                '--secondary' => 'oklch(0.278 0.033 256.848)',
                '--secondary-foreground' => 'oklch(0.985 0.002 247.839)',
                '--muted' => 'oklch(0.278 0.033 256.848)',
                '--muted-foreground' => 'oklch(0.707 0.022 261.325)',
                '--border' => 'oklch(1 0 0 / 10%)',
                '--input' => 'oklch(1 0 0 / 15%)',
                '--sidebar' => 'oklch(0.21 0.034 264.665)',
                '--sidebar-foreground' => 'oklch(0.985 0.002 247.839)',
                '--sidebar-border' => 'oklch(1 0 0 / 10%)',
                '--sidebar-ring' => 'oklch(0.551 0.027 264.364)',
            ],
        ],
    ];

    /**
     * @var array<int, array{key: string, label: string, primary: string, accent: string}>
     */
    private const THEME_COLOR_OPTIONS = [
        ['key' => 'neutral', 'label' => 'Monokrom', 'primary' => '#18181b', 'accent' => '#f4f4f5'],
        ['key' => 'orange', 'label' => 'Oranye', 'primary' => '#ea580c', 'accent' => '#fff7ed'],
        ['key' => 'blue', 'label' => 'Biru', 'primary' => '#2563eb', 'accent' => '#eff6ff'],
        ['key' => 'teal', 'label' => 'Teal', 'primary' => '#0f766e', 'accent' => '#f0fdfa'],
        ['key' => 'emerald', 'label' => 'Zamrud', 'primary' => '#059669', 'accent' => '#ecfdf5'],
        ['key' => 'indigo', 'label' => 'Nila', 'primary' => '#4338ca', 'accent' => '#eef2ff'],
        ['key' => 'violet', 'label' => 'Violet', 'primary' => '#7c3aed', 'accent' => '#f5f3ff'],
        ['key' => 'rose', 'label' => 'Merah Ros', 'primary' => '#e11d48', 'accent' => '#fff1f2'],
        ['key' => 'amber', 'label' => 'Amber', 'primary' => '#b45309', 'accent' => '#fffbeb'],
        ['key' => 'slate', 'label' => 'Batu Slate', 'primary' => '#1e293b', 'accent' => '#f1f5f9'],
    ];

    /**
     * @var array<string, array{
     *     key: string,
     *     label: string,
     *     swatch: string,
     *     light: array<string, string>,
     *     dark: array<string, string>
     * }>
     */
    private const CHART_COLOR_OPTIONS = [
        'orange' => [
            'key' => 'orange',
            'label' => 'Oranye',
            'swatch' => '#ea580c',
            'light' => [
                '--chart-1' => '#f97316',
                '--chart-2' => '#ea580c',
                '--chart-3' => '#fb923c',
                '--chart-4' => '#c2410c',
                '--chart-5' => '#fdba74',
            ],
            'dark' => [
                '--chart-1' => '#fb923c',
                '--chart-2' => '#f97316',
                '--chart-3' => '#fdba74',
                '--chart-4' => '#ea580c',
                '--chart-5' => '#fed7aa',
            ],
        ],
        'blue' => [
            'key' => 'blue',
            'label' => 'Biru',
            'swatch' => '#2563eb',
            'light' => [
                '--chart-1' => '#3b82f6',
                '--chart-2' => '#2563eb',
                '--chart-3' => '#60a5fa',
                '--chart-4' => '#1d4ed8',
                '--chart-5' => '#93c5fd',
            ],
            'dark' => [
                '--chart-1' => '#60a5fa',
                '--chart-2' => '#3b82f6',
                '--chart-3' => '#93c5fd',
                '--chart-4' => '#2563eb',
                '--chart-5' => '#bfdbfe',
            ],
        ],
        'teal' => [
            'key' => 'teal',
            'label' => 'Teal',
            'swatch' => '#0f766e',
            'light' => [
                '--chart-1' => '#14b8a6',
                '--chart-2' => '#0d9488',
                '--chart-3' => '#2dd4bf',
                '--chart-4' => '#0f766e',
                '--chart-5' => '#5eead4',
            ],
            'dark' => [
                '--chart-1' => '#2dd4bf',
                '--chart-2' => '#14b8a6',
                '--chart-3' => '#5eead4',
                '--chart-4' => '#0d9488',
                '--chart-5' => '#99f6e4',
            ],
        ],
        'emerald' => [
            'key' => 'emerald',
            'label' => 'Zamrud',
            'swatch' => '#059669',
            'light' => [
                '--chart-1' => '#10b981',
                '--chart-2' => '#059669',
                '--chart-3' => '#34d399',
                '--chart-4' => '#047857',
                '--chart-5' => '#6ee7b7',
            ],
            'dark' => [
                '--chart-1' => '#34d399',
                '--chart-2' => '#10b981',
                '--chart-3' => '#6ee7b7',
                '--chart-4' => '#059669',
                '--chart-5' => '#a7f3d0',
            ],
        ],
        'indigo' => [
            'key' => 'indigo',
            'label' => 'Nila',
            'swatch' => '#4338ca',
            'light' => [
                '--chart-1' => '#6366f1',
                '--chart-2' => '#4f46e5',
                '--chart-3' => '#818cf8',
                '--chart-4' => '#4338ca',
                '--chart-5' => '#a5b4fc',
            ],
            'dark' => [
                '--chart-1' => '#818cf8',
                '--chart-2' => '#6366f1',
                '--chart-3' => '#a5b4fc',
                '--chart-4' => '#4f46e5',
                '--chart-5' => '#c7d2fe',
            ],
        ],
        'violet' => [
            'key' => 'violet',
            'label' => 'Violet',
            'swatch' => '#7c3aed',
            'light' => [
                '--chart-1' => '#8b5cf6',
                '--chart-2' => '#7c3aed',
                '--chart-3' => '#a78bfa',
                '--chart-4' => '#6d28d9',
                '--chart-5' => '#c4b5fd',
            ],
            'dark' => [
                '--chart-1' => '#a78bfa',
                '--chart-2' => '#8b5cf6',
                '--chart-3' => '#c4b5fd',
                '--chart-4' => '#7c3aed',
                '--chart-5' => '#ddd6fe',
            ],
        ],
        'rose' => [
            'key' => 'rose',
            'label' => 'Merah Ros',
            'swatch' => '#e11d48',
            'light' => [
                '--chart-1' => '#f43f5e',
                '--chart-2' => '#e11d48',
                '--chart-3' => '#fb7185',
                '--chart-4' => '#be123c',
                '--chart-5' => '#fda4af',
            ],
            'dark' => [
                '--chart-1' => '#fb7185',
                '--chart-2' => '#f43f5e',
                '--chart-3' => '#fda4af',
                '--chart-4' => '#e11d48',
                '--chart-5' => '#fecdd3',
            ],
        ],
        'amber' => [
            'key' => 'amber',
            'label' => 'Amber',
            'swatch' => '#b45309',
            'light' => [
                '--chart-1' => '#f59e0b',
                '--chart-2' => '#d97706',
                '--chart-3' => '#fbbf24',
                '--chart-4' => '#b45309',
                '--chart-5' => '#fcd34d',
            ],
            'dark' => [
                '--chart-1' => '#fbbf24',
                '--chart-2' => '#f59e0b',
                '--chart-3' => '#fcd34d',
                '--chart-4' => '#d97706',
                '--chart-5' => '#fde68a',
            ],
        ],
        'neutral' => [
            'key' => 'neutral',
            'label' => 'Monokrom',
            'swatch' => '#52525b',
            'light' => [
                '--chart-1' => '#27272a',
                '--chart-2' => '#52525b',
                '--chart-3' => '#71717a',
                '--chart-4' => '#a1a1aa',
                '--chart-5' => '#d4d4d8',
            ],
            'dark' => [
                '--chart-1' => '#e4e4e7',
                '--chart-2' => '#a1a1aa',
                '--chart-3' => '#71717a',
                '--chart-4' => '#52525b',
                '--chart-5' => '#3f3f46',
            ],
        ],
    ];

    /**
     * @var array<int, array{key: string, label: string, category: string, google_family: string, css_stack: string}>
     */
    private const FONT_OPTIONS = [
        [
            'key' => 'Instrument Sans',
            'label' => 'Instrument Sans',
            'category' => 'Sans',
            'google_family' => 'Instrument+Sans:wght@400;500;600;700',
            'css_stack' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
        ],
        [
            'key' => 'Inter',
            'label' => 'Inter',
            'category' => 'Sans',
            'google_family' => 'Inter:wght@400;500;600;700',
            'css_stack' => "'Inter', ui-sans-serif, system-ui, sans-serif",
        ],
        [
            'key' => 'Outfit',
            'label' => 'Outfit',
            'category' => 'Geometris',
            'google_family' => 'Outfit:wght@400;500;600;700',
            'css_stack' => "'Outfit', ui-sans-serif, system-ui, sans-serif",
        ],
        [
            'key' => 'Oxanium',
            'label' => 'Oxanium',
            'category' => 'Display',
            'google_family' => 'Oxanium:wght@400;500;600;700',
            'css_stack' => "'Oxanium', ui-sans-serif, system-ui, sans-serif",
        ],
        [
            'key' => 'Plus Jakarta Sans',
            'label' => 'Plus Jakarta Sans',
            'category' => 'Humanis',
            'google_family' => 'Plus+Jakarta+Sans:wght@400;500;600;700',
            'css_stack' => "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif",
        ],
        [
            'key' => 'Manrope',
            'label' => 'Manrope',
            'category' => 'Sans',
            'google_family' => 'Manrope:wght@400;500;600;700',
            'css_stack' => "'Manrope', ui-sans-serif, system-ui, sans-serif",
        ],
        [
            'key' => 'DM Sans',
            'label' => 'DM Sans',
            'category' => 'Sans',
            'google_family' => 'DM+Sans:wght@400;500;600;700',
            'css_stack' => "'DM Sans', ui-sans-serif, system-ui, sans-serif",
        ],
        [
            'key' => 'Poppins',
            'label' => 'Poppins',
            'category' => 'Geometris',
            'google_family' => 'Poppins:wght@400;500;600;700',
            'css_stack' => "'Poppins', ui-sans-serif, system-ui, sans-serif",
        ],
        [
            'key' => 'Space Grotesk',
            'label' => 'Space Grotesk',
            'category' => 'Editorial',
            'google_family' => 'Space+Grotesk:wght@400;500;600;700',
            'css_stack' => "'Space Grotesk', ui-sans-serif, system-ui, sans-serif",
        ],
        [
            'key' => 'JetBrains Mono',
            'label' => 'JetBrains Mono',
            'category' => 'Mono',
            'google_family' => 'JetBrains+Mono:wght@400;500;600;700',
            'css_stack' => "'JetBrains Mono', ui-monospace, SFMono-Regular, monospace",
        ],
    ];

    /**
     * @var array<int, array{value: string, label: string}>
     */
    private const RADIUS_OPTIONS = [
        ['value' => '0rem', 'label' => 'None (0rem)'],
        ['value' => '0.25rem', 'label' => 'Ringkas (0.25rem)'],
        ['value' => '0.375rem', 'label' => 'Sedang (0.375rem)'],
        ['value' => '0.5rem', 'label' => 'Standar (0.5rem)'],
        ['value' => '0.625rem', 'label' => 'Bawaan (0.625rem)'],
        ['value' => '0.75rem', 'label' => 'Lembut (0.75rem)'],
        ['value' => '1rem', 'label' => 'Bulat (1rem)'],
    ];

    /**
     * @var array<int, array{key: string, label: string, description: string}>
     */
    private const MENU_STYLE_OPTIONS = [
        ['key' => 'inset', 'label' => 'Inset / Menyatu', 'description' => 'Sidebar menyatu dengan kanvas konten utama.'],
        ['key' => 'sidebar', 'label' => 'Default / Solid', 'description' => 'Sidebar penuh dengan garis batas tegas.'],
        ['key' => 'floating', 'label' => 'Floating / Kartu', 'description' => 'Sidebar mengambang dengan sudut dan border.'],
        ['key' => 'inverted', 'label' => 'Kontras Gelap', 'description' => 'Sidebar gelap permanen pada mode terang.'],
    ];

    /**
     * @var array<int, array{key: string, label: string, description: string}>
     */
    private const MENU_ACCENT_OPTIONS = [
        ['key' => 'subtle', 'label' => 'Subtle', 'description' => 'Menu aktif disorot dengan warna aksen lembut.'],
        ['key' => 'bold', 'label' => 'Solid / Bold', 'description' => 'Menu aktif memakai warna utama penuh.'],
    ];

    /**
     * @var array<int, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     style: string,
     *     base_color: string,
     *     theme_color: string,
     *     primary: string,
     *     accent: string,
     *     chart_color: string,
     *     heading_font: string,
     *     body_font: string,
     *     radius: string,
     *     menu_style: string,
     *     menu_accent: string
     * }>
     */
    private const PRESETS = [
        [
            'key' => 'lyra-oranye',
            'label' => 'Lyra Oranye',
            'description' => 'Tajam, hangat (Stone + Oranye), dan tipografi Oxanium + Outfit.',
            'style' => 'lyra',
            'base_color' => 'stone',
            'theme_color' => 'orange',
            'primary' => '#ea580c',
            'accent' => '#fff7ed',
            'chart_color' => 'orange',
            'heading_font' => 'Oxanium',
            'body_font' => 'Outfit',
            'radius' => '0rem',
            'menu_style' => 'sidebar',
            'menu_accent' => 'subtle',
        ],
        [
            'key' => 'samudra',
            'label' => 'Samudra Biru',
            'description' => 'Nuansa birokrasi formal dengan aksen biru bersih.',
            'style' => 'vega',
            'base_color' => 'slate',
            'theme_color' => 'blue',
            'primary' => '#1d4ed8',
            'accent' => '#eff6ff',
            'chart_color' => 'blue',
            'heading_font' => 'Plus Jakarta Sans',
            'body_font' => 'Inter',
            'radius' => '0.5rem',
            'menu_style' => 'inset',
            'menu_accent' => 'subtle',
        ],
        [
            'key' => 'zamrud',
            'label' => 'Zamrud Layanan',
            'description' => 'Hijau tua tenang untuk portal layanan publik dan klinik.',
            'style' => 'maia',
            'base_color' => 'stone',
            'theme_color' => 'teal',
            'primary' => '#0f766e',
            'accent' => '#f0fdfa',
            'chart_color' => 'teal',
            'heading_font' => 'Manrope',
            'body_font' => 'Instrument Sans',
            'radius' => '0.625rem',
            'menu_style' => 'inset',
            'menu_accent' => 'bold',
        ],
        [
            'key' => 'batu',
            'label' => 'Batu Slate',
            'description' => 'Monokrom tegas dengan sudut ringkas dan kontras tinggi.',
            'style' => 'nova',
            'base_color' => 'zinc',
            'theme_color' => 'slate',
            'primary' => '#1e293b',
            'accent' => '#f1f5f9',
            'chart_color' => 'neutral',
            'heading_font' => 'Space Grotesk',
            'body_font' => 'Inter',
            'radius' => '0.375rem',
            'menu_style' => 'inverted',
            'menu_accent' => 'bold',
        ],
        [
            'key' => 'terakota',
            'label' => 'Terakota Hangat',
            'description' => 'Karakter hangat untuk institusi pendidikan dan komunitas.',
            'style' => 'maia',
            'base_color' => 'stone',
            'theme_color' => 'amber',
            'primary' => '#b45309',
            'accent' => '#fffbeb',
            'chart_color' => 'amber',
            'heading_font' => 'Poppins',
            'body_font' => 'DM Sans',
            'radius' => '0.75rem',
            'menu_style' => 'floating',
            'menu_accent' => 'subtle',
        ],
        [
            'key' => 'nila',
            'label' => 'Nila Klasik',
            'description' => 'Aksen indigo terstruktur untuk perusahaan dan kampus.',
            'style' => 'mira',
            'base_color' => 'gray',
            'theme_color' => 'indigo',
            'primary' => '#4338ca',
            'accent' => '#eef2ff',
            'chart_color' => 'indigo',
            'heading_font' => 'Inter',
            'body_font' => 'Inter',
            'radius' => '0.25rem',
            'menu_style' => 'sidebar',
            'menu_accent' => 'bold',
        ],
    ];

    /**
     * @return array<int, string>
     */
    public function allowedStyles(): array
    {
        return array_column(self::STYLE_OPTIONS, 'key');
    }

    /**
     * @return array<int, string>
     */
    public function allowedBaseColors(): array
    {
        return array_keys(self::BASE_COLOR_OPTIONS);
    }

    /**
     * @return array<int, string>
     */
    public function allowedThemeColors(): array
    {
        return [...array_column(self::THEME_COLOR_OPTIONS, 'key'), 'custom'];
    }

    /**
     * @return array<int, string>
     */
    public function allowedChartColors(): array
    {
        return array_keys(self::CHART_COLOR_OPTIONS);
    }

    /**
     * @return array<int, string>
     */
    public function allowedFonts(): array
    {
        return array_column(self::FONT_OPTIONS, 'key');
    }

    /**
     * @return array<int, string>
     */
    public function allowedMenuStyles(): array
    {
        return array_column(self::MENU_STYLE_OPTIONS, 'key');
    }

    /**
     * @return array<int, string>
     */
    public function allowedMenuAccents(): array
    {
        return array_column(self::MENU_ACCENT_OPTIONS, 'key');
    }

    public function isValidChartColor(string $value): bool
    {
        return isset(self::CHART_COLOR_OPTIONS[$value]) || $this->isValidColor($value);
    }

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
     * @param  array<string, mixed>|null  $themeConfig
     * @return array<string, string>
     */
    public function formatCssVariables(?array $themeConfig, bool $dark = false): array
    {
        if (! is_array($themeConfig) || $themeConfig === []) {
            return [];
        }

        $variables = [];
        $modeKey = $dark ? 'dark' : 'light';

        $baseColorKey = $this->resolveOptionKey($themeConfig['base_color'] ?? null, $this->allowedBaseColors());
        if ($baseColorKey !== null) {
            foreach (self::BASE_COLOR_OPTIONS[$baseColorKey][$modeKey] as $var => $val) {
                $variables[$var] = $val;
            }
        }

        $resolvedPrimary = null;
        $primaryForeground = null;
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

        $menuStyle = $this->resolveOptionKey($themeConfig['menu_style'] ?? null, $this->allowedMenuStyles());
        $menuAccent = $this->resolveOptionKey($themeConfig['menu_accent'] ?? null, $this->allowedMenuAccents());

        $isInvertedSidebar = ! $dark && $menuStyle === 'inverted';
        if ($isInvertedSidebar) {
            $darkSidebarPalette = self::BASE_COLOR_OPTIONS[$baseColorKey ?? self::DEFAULT_BASE_COLOR]['dark'];
            $variables['--sidebar'] = $darkSidebarPalette['--sidebar'];
            $variables['--sidebar-foreground'] = $darkSidebarPalette['--sidebar-foreground'];
            $variables['--sidebar-border'] = $darkSidebarPalette['--sidebar-border'];
            $variables['--sidebar-ring'] = $darkSidebarPalette['--sidebar-ring'];
        }

        $accent = $this->extractValidColor($themeConfig['accent'] ?? null);
        if ($accent !== null) {
            $resolvedAccent = $dark ? $this->adaptAccentForDarkSurface($accent) : $accent;
            $accentForeground = $this->pickContrastingForeground($resolvedAccent);

            $variables['--accent'] = $resolvedAccent;
            $variables['--accent-foreground'] = $accentForeground;

            $sidebarAccentSurface = $isInvertedSidebar ? $this->adaptAccentForDarkSurface($accent) : $resolvedAccent;
            $variables['--sidebar-accent'] = $sidebarAccentSurface;
            $variables['--sidebar-accent-foreground'] = $this->pickContrastingForeground($sidebarAccentSurface);
        }

        if ($menuAccent === 'bold' && $resolvedPrimary !== null) {
            $variables['--sidebar-accent'] = $resolvedPrimary;
            $variables['--sidebar-accent-foreground'] = $primaryForeground;
        }

        $chartColor = $themeConfig['chart_color'] ?? null;
        if (is_string($chartColor) && trim($chartColor) !== '') {
            $trimmedChart = trim($chartColor);
            if (isset(self::CHART_COLOR_OPTIONS[$trimmedChart])) {
                foreach (self::CHART_COLOR_OPTIONS[$trimmedChart][$modeKey] as $var => $val) {
                    $variables[$var] = $val;
                }
            } elseif ($this->isValidColor($trimmedChart)) {
                $normalizedChart = $this->normalizeColor($trimmedChart);
                foreach ($this->generateCustomChartVariables($normalizedChart, $dark) as $var => $val) {
                    $variables[$var] = $val;
                }
            }
        }

        $bodyFontKey = $this->resolveOptionKey($themeConfig['body_font'] ?? null, $this->allowedFonts());
        if ($bodyFontKey !== null) {
            $fontOption = $this->findFontOption($bodyFontKey);
            if ($fontOption !== null) {
                $variables['--font-sans'] = $fontOption['css_stack'];
            }
        }

        $headingFontKey = $this->resolveOptionKey($themeConfig['heading_font'] ?? null, $this->allowedFonts());
        if ($headingFontKey !== null) {
            $fontOption = $this->findFontOption($headingFontKey);
            if ($fontOption !== null) {
                $variables['--font-heading'] = $fontOption['css_stack'];
            }
        }

        $radius = $themeConfig['radius'] ?? null;
        if (is_string($radius) && trim($radius) !== '') {
            $variables['--radius'] = trim($radius);
        }

        return $variables;
    }

    public function buildFontStylesheetUrl(?string $headingFont, ?string $bodyFont): ?string
    {
        $families = [];

        foreach ([$headingFont, $bodyFont] as $fontKey) {
            if (! is_string($fontKey) || $fontKey === '' || $fontKey === self::DEFAULT_BODY_FONT) {
                continue;
            }

            $option = $this->findFontOption($fontKey);
            if ($option !== null) {
                $families[$option['key']] = 'family='.$option['google_family'];
            }
        }

        if ($families === []) {
            return null;
        }

        return 'https://fonts.googleapis.com/css2?'.implode('&', array_values($families)).'&display=swap';
    }

    /**
     * @param  array<string, string>  $config
     */
    public function encodePresetCode(array $config): string
    {
        $compact = [
            's' => $config['style'] ?? self::DEFAULT_STYLE,
            'b' => $config['base_color'] ?? self::DEFAULT_BASE_COLOR,
            't' => $config['theme_color'] ?? self::DEFAULT_THEME_COLOR,
            'p' => $config['primary'] ?? '#18181b',
            'a' => $config['accent'] ?? '#f4f4f5',
            'c' => $config['chart_color'] ?? self::DEFAULT_CHART_COLOR,
            'hf' => $config['heading_font'] ?? self::DEFAULT_HEADING_FONT,
            'bf' => $config['body_font'] ?? self::DEFAULT_BODY_FONT,
            'r' => $config['radius'] ?? self::DEFAULT_RADIUS,
            'ms' => $config['menu_style'] ?? self::DEFAULT_MENU_STYLE,
            'ma' => $config['menu_accent'] ?? self::DEFAULT_MENU_ACCENT,
        ];

        $json = json_encode($compact, JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            return '';
        }

        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        return self::PRESET_PREFIX.$encoded;
    }

    /**
     * @return array<string, string>|null
     */
    public function decodePresetCode(string $code): ?array
    {
        $trimmed = trim($code);
        if (str_starts_with($trimmed, '--preset ')) {
            $trimmed = trim(substr($trimmed, 9));
        }

        if (! str_starts_with($trimmed, self::PRESET_PREFIX)) {
            return null;
        }

        $payload = substr($trimmed, strlen(self::PRESET_PREFIX));
        if ($payload === '') {
            return null;
        }

        $padded = str_pad(strtr($payload, '-_', '+/'), strlen($payload) % 4 === 0 ? strlen($payload) : strlen($payload) + (4 - strlen($payload) % 4), '=');
        $decodedJson = base64_decode($padded, true);
        if (! is_string($decodedJson)) {
            return null;
        }

        $data = json_decode($decodedJson, true);
        if (! is_array($data)) {
            return null;
        }

        $primary = $this->extractValidColor($data['p'] ?? null);
        $accent = $this->extractValidColor($data['a'] ?? null);
        $radius = is_string($data['r'] ?? null) ? trim($data['r']) : null;

        if ($primary === null || $accent === null || $radius === null || $radius === '') {
            return null;
        }

        return [
            'style' => $this->resolveOptionKey($data['s'] ?? null, $this->allowedStyles()) ?? self::DEFAULT_STYLE,
            'base_color' => $this->resolveOptionKey($data['b'] ?? null, $this->allowedBaseColors()) ?? self::DEFAULT_BASE_COLOR,
            'theme_color' => $this->resolveOptionKey($data['t'] ?? null, $this->allowedThemeColors()) ?? 'custom',
            'primary' => $primary,
            'accent' => $accent,
            'chart_color' => is_string($data['c'] ?? null) && $this->isValidChartColor($data['c'])
                ? (isset(self::CHART_COLOR_OPTIONS[$data['c']]) ? $data['c'] : $this->normalizeColor($data['c']))
                : self::DEFAULT_CHART_COLOR,
            'heading_font' => $this->resolveOptionKey($data['hf'] ?? null, $this->allowedFonts()) ?? self::DEFAULT_HEADING_FONT,
            'body_font' => $this->resolveOptionKey($data['bf'] ?? null, $this->allowedFonts()) ?? self::DEFAULT_BODY_FONT,
            'radius' => $radius,
            'menu_style' => $this->resolveOptionKey($data['ms'] ?? null, $this->allowedMenuStyles()) ?? self::DEFAULT_MENU_STYLE,
            'menu_accent' => $this->resolveOptionKey($data['ma'] ?? null, $this->allowedMenuAccents()) ?? self::DEFAULT_MENU_ACCENT,
        ];
    }

    /**
     * @return array{
     *     config: array{
     *         style: string,
     *         base_color: string,
     *         theme_color: string,
     *         primary: string|null,
     *         accent: string|null,
     *         chart_color: string,
     *         heading_font: string,
     *         body_font: string,
     *         radius: string|null,
     *         menu_style: string,
     *         menu_accent: string
     *     }|null,
     *     css_variables: array<string, string>,
     *     dark_css_variables: array<string, string>,
     *     font_stylesheet_url: string|null,
     *     preset_code: string|null,
     *     data_attributes: array{style: string, menu_style: string, menu_accent: string}|null
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
                'font_stylesheet_url' => null,
                'preset_code' => null,
                'data_attributes' => null,
            ];
        }

        $primary = $this->extractValidColor($rawConfig['primary'] ?? null);
        $accent = $this->extractValidColor($rawConfig['accent'] ?? null);
        $radius = isset($rawConfig['radius']) && trim($rawConfig['radius']) !== ''
            ? trim($rawConfig['radius'])
            : null;

        if ($primary === null && $accent === null && $radius === null) {
            return [
                'config' => null,
                'css_variables' => [],
                'dark_css_variables' => [],
                'font_stylesheet_url' => null,
                'preset_code' => null,
                'data_attributes' => null,
            ];
        }

        $style = $this->resolveOptionKey($rawConfig['style'] ?? null, $this->allowedStyles()) ?? self::DEFAULT_STYLE;
        $baseColor = $this->resolveOptionKey($rawConfig['base_color'] ?? null, $this->allowedBaseColors()) ?? self::DEFAULT_BASE_COLOR;
        $themeColor = $this->resolveOptionKey($rawConfig['theme_color'] ?? null, $this->allowedThemeColors())
            ?? $this->inferThemeColorKey($primary);

        $rawChartColor = $rawConfig['chart_color'] ?? null;
        $chartColor = is_string($rawChartColor) && $this->isValidChartColor($rawChartColor)
            ? (isset(self::CHART_COLOR_OPTIONS[$rawChartColor]) ? $rawChartColor : $this->normalizeColor($rawChartColor))
            : self::DEFAULT_CHART_COLOR;

        $headingFont = $this->resolveOptionKey($rawConfig['heading_font'] ?? null, $this->allowedFonts()) ?? self::DEFAULT_HEADING_FONT;
        $bodyFont = $this->resolveOptionKey($rawConfig['body_font'] ?? null, $this->allowedFonts()) ?? self::DEFAULT_BODY_FONT;
        $menuStyle = $this->resolveOptionKey($rawConfig['menu_style'] ?? null, $this->allowedMenuStyles()) ?? self::DEFAULT_MENU_STYLE;
        $menuAccent = $this->resolveOptionKey($rawConfig['menu_accent'] ?? null, $this->allowedMenuAccents()) ?? self::DEFAULT_MENU_ACCENT;

        $resolvedConfig = [
            'style' => $style,
            'base_color' => $baseColor,
            'theme_color' => $themeColor,
            'primary' => $primary,
            'accent' => $accent,
            'chart_color' => $chartColor,
            'heading_font' => $headingFont,
            'body_font' => $bodyFont,
            'radius' => $radius,
            'menu_style' => $menuStyle,
            'menu_accent' => $menuAccent,
        ];

        $formatInput = array_filter([
            'style' => $style,
            'base_color' => isset($rawConfig['base_color']) ? $baseColor : null,
            'primary' => $primary,
            'accent' => $accent,
            'chart_color' => isset($rawConfig['chart_color']) ? $chartColor : null,
            'heading_font' => isset($rawConfig['heading_font']) ? $headingFont : null,
            'body_font' => isset($rawConfig['body_font']) ? $bodyFont : null,
            'radius' => $radius,
            'menu_style' => isset($rawConfig['menu_style']) ? $menuStyle : null,
            'menu_accent' => isset($rawConfig['menu_accent']) ? $menuAccent : null,
        ], static fn (?string $value): bool => $value !== null);

        $presetCode = $this->encodePresetCode([
            'style' => $style,
            'base_color' => $baseColor,
            'theme_color' => $themeColor,
            'primary' => $primary ?? '#18181b',
            'accent' => $accent ?? '#f4f4f5',
            'chart_color' => $chartColor,
            'heading_font' => $headingFont,
            'body_font' => $bodyFont,
            'radius' => $radius ?? self::DEFAULT_RADIUS,
            'menu_style' => $menuStyle,
            'menu_accent' => $menuAccent,
        ]);

        return [
            'config' => $resolvedConfig,
            'css_variables' => $this->formatCssVariables($formatInput, false),
            'dark_css_variables' => $this->formatCssVariables($formatInput, true),
            'font_stylesheet_url' => $this->buildFontStylesheetUrl(
                isset($rawConfig['heading_font']) ? $headingFont : null,
                isset($rawConfig['body_font']) ? $bodyFont : null,
            ),
            'preset_code' => $presetCode,
            'data_attributes' => [
                'style' => $style,
                'menu_style' => $menuStyle,
                'menu_accent' => $menuAccent,
            ],
        ];
    }

    /**
     * @return array{
     *     config: array{
     *         style: string,
     *         base_color: string,
     *         theme_color: string,
     *         primary: string|null,
     *         accent: string|null,
     *         chart_color: string,
     *         heading_font: string,
     *         body_font: string,
     *         radius: string|null,
     *         menu_style: string,
     *         menu_accent: string
     *     }|null,
     *     css_variables: array<string, string>,
     *     dark_css_variables: array<string, string>,
     *     font_stylesheet_url: string|null,
     *     preset_code: string|null,
     *     data_attributes: array{style: string, menu_style: string, menu_accent: string}|null,
     *     presets: array<int, array{
     *         key: string,
     *         label: string,
     *         description: string,
     *         style: string,
     *         base_color: string,
     *         theme_color: string,
     *         primary: string,
     *         accent: string,
     *         chart_color: string,
     *         heading_font: string,
     *         body_font: string,
     *         radius: string,
     *         menu_style: string,
     *         menu_accent: string
     *     }>,
     *     style_options: array<int, array{key: string, label: string, description: string, default_radius: string}>,
     *     base_color_options: array<int, array{key: string, label: string, swatch: string, light: array<string, string>, dark: array<string, string>}>,
     *     theme_color_options: array<int, array{key: string, label: string, primary: string, accent: string}>,
     *     chart_color_options: array<int, array{key: string, label: string, swatch: string, light: array<string, string>, dark: array<string, string>}>,
     *     font_options: array<int, array{key: string, label: string, category: string, google_family: string, css_stack: string}>,
     *     radius_options: array<int, array{value: string, label: string}>,
     *     menu_style_options: array<int, array{key: string, label: string, description: string}>,
     *     menu_accent_options: array<int, array{key: string, label: string, description: string}>,
     *     defaults: array{
     *         style: string,
     *         base_color: string,
     *         theme_color: string,
     *         primary: string,
     *         accent: string,
     *         chart_color: string,
     *         heading_font: string,
     *         body_font: string,
     *         radius: string,
     *         menu_style: string,
     *         menu_accent: string
     *     }
     * }
     */
    public function getEditorData(Tenant $tenant): array
    {
        return [
            ...$this->resolveThemePayload($tenant),
            'presets' => self::PRESETS,
            'style_options' => self::STYLE_OPTIONS,
            'base_color_options' => array_values(self::BASE_COLOR_OPTIONS),
            'theme_color_options' => self::THEME_COLOR_OPTIONS,
            'chart_color_options' => array_values(self::CHART_COLOR_OPTIONS),
            'font_options' => self::FONT_OPTIONS,
            'radius_options' => self::RADIUS_OPTIONS,
            'menu_style_options' => self::MENU_STYLE_OPTIONS,
            'menu_accent_options' => self::MENU_ACCENT_OPTIONS,
            'defaults' => [
                'style' => self::DEFAULT_STYLE,
                'base_color' => self::DEFAULT_BASE_COLOR,
                'theme_color' => self::DEFAULT_THEME_COLOR,
                'primary' => '#18181b',
                'accent' => '#f4f4f5',
                'chart_color' => self::DEFAULT_CHART_COLOR,
                'heading_font' => self::DEFAULT_HEADING_FONT,
                'body_font' => self::DEFAULT_BODY_FONT,
                'radius' => self::DEFAULT_RADIUS,
                'menu_style' => self::DEFAULT_MENU_STYLE,
                'menu_accent' => self::DEFAULT_MENU_ACCENT,
            ],
        ];
    }

    /**
     * @param  array{
     *     primary: string,
     *     accent: string,
     *     radius: string,
     *     style?: string|null,
     *     base_color?: string|null,
     *     theme_color?: string|null,
     *     chart_color?: string|null,
     *     heading_font?: string|null,
     *     body_font?: string|null,
     *     menu_style?: string|null,
     *     menu_accent?: string|null
     * }  $data
     */
    public function updateTheme(Tenant $tenant, array $data): Tenant
    {
        $config = [
            'primary' => $this->normalizeColor($data['primary']),
            'accent' => $this->normalizeColor($data['accent']),
            'radius' => trim($data['radius']),
        ];

        $optionalFields = [
            'style' => $this->allowedStyles(),
            'base_color' => $this->allowedBaseColors(),
            'theme_color' => $this->allowedThemeColors(),
            'heading_font' => $this->allowedFonts(),
            'body_font' => $this->allowedFonts(),
            'menu_style' => $this->allowedMenuStyles(),
            'menu_accent' => $this->allowedMenuAccents(),
        ];

        foreach ($optionalFields as $field => $allowedValues) {
            if (array_key_exists($field, $data)) {
                $resolved = $this->resolveOptionKey($data[$field], $allowedValues);
                if ($resolved !== null) {
                    $config[$field] = $resolved;
                }
            }
        }

        if (array_key_exists('chart_color', $data) && is_string($data['chart_color'])) {
            $chartColor = trim($data['chart_color']);
            if (isset(self::CHART_COLOR_OPTIONS[$chartColor])) {
                $config['chart_color'] = $chartColor;
            } elseif ($this->isValidColor($chartColor)) {
                $config['chart_color'] = $this->normalizeColor($chartColor);
            }
        }

        $tenant->theme_config = $config;
        $tenant->save();

        return $tenant;
    }

    public function resetTheme(Tenant $tenant): Tenant
    {
        $tenant->theme_config = null;
        $tenant->save();

        return $tenant;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function resolveOptionKey(mixed $value, array $allowed): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return in_array($trimmed, $allowed, true) ? $trimmed : null;
    }

    /**
     * @return array{key: string, label: string, category: string, google_family: string, css_stack: string}|null
     */
    private function findFontOption(string $key): ?array
    {
        foreach (self::FONT_OPTIONS as $option) {
            if ($option['key'] === $key) {
                return $option;
            }
        }

        return null;
    }

    private function inferThemeColorKey(?string $normalizedPrimary): string
    {
        if ($normalizedPrimary === null) {
            return self::DEFAULT_THEME_COLOR;
        }

        foreach (self::THEME_COLOR_OPTIONS as $option) {
            if (strtolower($option['primary']) === strtolower($normalizedPrimary)) {
                return $option['key'];
            }
        }

        return 'custom';
    }

    /**
     * @return array<string, string>
     */
    private function generateCustomChartVariables(string $normalizedColor, bool $dark): array
    {
        [$r, $g, $b] = $this->toRgb($normalizedColor);
        [$h, $s, $l] = $this->rgbToHsl($r, $g, $b);

        $lightnessSteps = $dark
            ? [max(58.0, min(74.0, $l + 18.0)), max(50.0, min(65.0, $l + 10.0)), max(65.0, min(80.0, $l + 24.0)), max(42.0, min(58.0, $l)), max(72.0, min(86.0, $l + 30.0))]
            : [$l, max(30.0, $l - 10.0), min(70.0, $l + 12.0), max(24.0, $l - 18.0), min(78.0, $l + 22.0)];

        return [
            '--chart-1' => $this->hslToHex($h, $s, $lightnessSteps[0]),
            '--chart-2' => $this->hslToHex($h, min(95.0, $s + 5.0), $lightnessSteps[1]),
            '--chart-3' => $this->hslToHex($h, max(20.0, $s - 8.0), $lightnessSteps[2]),
            '--chart-4' => $this->hslToHex($h, min(95.0, $s + 8.0), $lightnessSteps[3]),
            '--chart-5' => $this->hslToHex($h, max(18.0, $s - 12.0), $lightnessSteps[4]),
        ];
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
