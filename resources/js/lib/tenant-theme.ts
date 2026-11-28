import type { TenantThemeConfig, TenantThemeEditorData } from '@/types';

export type ThemeFormValues = {
    style: string;
    base_color: string;
    theme_color: string;
    primary: string;
    accent: string;
    chart_color: string;
    heading_font: string;
    body_font: string;
    radius: string;
    menu_style: string;
    menu_accent: string;
};

const HEX_REGEX = /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/;
const HSL_REGEX =
    /^hsl\(\s*(\d{1,3}(?:\.\d+)?)\s*(?:,|\s)\s*(\d{1,3}(?:\.\d+)?)%\s*(?:,|\s)\s*(\d{1,3}(?:\.\d+)?)%\s*\)$/i;
const PRESET_PREFIX = 'prs_';

function hslToHex(h: number, s: number, l: number): string {
    const hue = ((h % 360) + 360) % 360;
    const sat = Math.max(0, Math.min(100, s)) / 100;
    const light = Math.max(0, Math.min(100, l)) / 100;

    const a = sat * Math.min(light, 1 - light);
    const channel = (n: number) => {
        const k = (n + hue / 30) % 12;
        const color = light - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);

        return Math.round(255 * color)
            .toString(16)
            .padStart(2, '0');
    };

    return `#${channel(0)}${channel(8)}${channel(4)}`;
}

function hexToRgb(hex: string): [number, number, number] {
    return [
        parseInt(hex.slice(1, 3), 16),
        parseInt(hex.slice(3, 5), 16),
        parseInt(hex.slice(5, 7), 16),
    ];
}

function rgbToHsl(r: number, g: number, b: number): [number, number, number] {
    const rf = r / 255;
    const gf = g / 255;
    const bf = b / 255;

    const max = Math.max(rf, gf, bf);
    const min = Math.min(rf, gf, bf);
    const l = (max + min) / 2;

    if (max === min) {
        return [0, 0, l * 100];
    }

    const d = max - min;
    const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

    let h = 0;
    if (max === rf) {
        h = (gf - bf) / d + (gf < bf ? 6 : 0);
    } else if (max === gf) {
        h = (bf - rf) / d + 2;
    } else {
        h = (rf - gf) / d + 4;
    }

    return [(h / 6) * 360, s * 100, l * 100];
}

export function toHexForPicker(value: string, fallback: string): string {
    const trimmed = value.trim();

    if (HEX_REGEX.test(trimmed)) {
        if (trimmed.length === 4) {
            return `#${trimmed[1]}${trimmed[1]}${trimmed[2]}${trimmed[2]}${trimmed[3]}${trimmed[3]}`.toLowerCase();
        }

        return trimmed.toLowerCase();
    }

    const hslMatch = trimmed.match(HSL_REGEX);
    if (hslMatch) {
        return hslToHex(
            Number(hslMatch[1]),
            Number(hslMatch[2]),
            Number(hslMatch[3]),
        );
    }

    return fallback;
}

export function getRelativeLuminance(hex: string): number {
    const clean = toHexForPicker(hex, '#18181b');
    const [r, g, b] = hexToRgb(clean).map((c) => c / 255);

    const linearize = (channel: number) =>
        channel <= 0.03928
            ? channel / 12.92
            : Math.pow((channel + 0.055) / 1.055, 2.4);

    return (
        0.2126 * linearize(r) + 0.7152 * linearize(g) + 0.0722 * linearize(b)
    );
}

export function resolveForegroundAndRatio(
    colorValue: string,
    fallback: string,
): {
    isValid: boolean;
    hex: string;
    foreground: string;
    contrastRatio: number;
} {
    const trimmed = colorValue.trim();
    const isValid = HEX_REGEX.test(trimmed) || HSL_REGEX.test(trimmed);
    const hex = toHexForPicker(trimmed, fallback);
    const lum = getRelativeLuminance(hex);
    const foreground = lum > 0.179 ? '#09090b' : '#ffffff';
    const fgLum = foreground === '#ffffff' ? 1 : 0.002;
    const lighter = Math.max(lum, fgLum);
    const darker = Math.min(lum, fgLum);
    const contrastRatio = (lighter + 0.05) / (darker + 0.05);

    return {
        isValid,
        hex,
        foreground,
        contrastRatio,
    };
}

function adaptColorForDarkSurface(hex: string, minLuminance: number): string {
    if (getRelativeLuminance(hex) >= minLuminance) {
        return hex;
    }

    const [r, g, b] = hexToRgb(hex);
    const [h, s, l] = rgbToHsl(r, g, b);

    return hslToHex(h, s, Math.max(l, 65));
}

function adaptAccentForDarkSurface(hex: string): string {
    if (getRelativeLuminance(hex) <= 0.28) {
        return hex;
    }

    const [r, g, b] = hexToRgb(hex);
    const [h, s] = rgbToHsl(r, g, b);

    return hslToHex(h, Math.min(s, 36), 22);
}

export function buildPreviewFontUrl(
    editorData: TenantThemeEditorData,
    headingFont: string,
    bodyFont: string,
): string | null {
    const families = new Map<string, string>();

    for (const key of [headingFont, bodyFont]) {
        if (!key || key === editorData.defaults.body_font) {
            continue;
        }

        const option = editorData.font_options.find((item) => item.key === key);
        if (option) {
            families.set(option.key, `family=${option.google_family}`);
        }
    }

    if (families.size === 0) {
        return null;
    }

    return `https://fonts.googleapis.com/css2?${Array.from(families.values()).join('&')}&display=swap`;
}

export function buildPreviewCssVariables(
    values: ThemeFormValues,
    editorData: TenantThemeEditorData,
    dark: boolean,
): Record<string, string> {
    const vars: Record<string, string> = {};
    const mode = dark ? 'dark' : 'light';

    const baseOption = editorData.base_color_options.find(
        (item) => item.key === values.base_color,
    );
    if (baseOption) {
        Object.assign(vars, baseOption[mode]);
    }

    const primaryHex = toHexForPicker(
        values.primary,
        editorData.defaults.primary,
    );
    const resolvedPrimary = dark
        ? adaptColorForDarkSurface(primaryHex, 0.25)
        : primaryHex;
    const primaryFg = resolveForegroundAndRatio(
        resolvedPrimary,
        '#18181b',
    ).foreground;

    vars['--primary'] = resolvedPrimary;
    vars['--primary-foreground'] = primaryFg;
    vars['--sidebar-primary'] = resolvedPrimary;
    vars['--sidebar-primary-foreground'] = primaryFg;
    vars['--ring'] = resolvedPrimary;

    const isInvertedSidebar = !dark && values.menu_style === 'inverted';
    if (isInvertedSidebar && baseOption) {
        vars['--sidebar'] = baseOption.dark['--sidebar'];
        vars['--sidebar-foreground'] = baseOption.dark['--sidebar-foreground'];
        vars['--sidebar-border'] = baseOption.dark['--sidebar-border'];
        vars['--sidebar-ring'] = baseOption.dark['--sidebar-ring'];
    }

    const accentHex = toHexForPicker(values.accent, editorData.defaults.accent);
    const resolvedAccent = dark
        ? adaptAccentForDarkSurface(accentHex)
        : accentHex;
    const accentFg = resolveForegroundAndRatio(
        resolvedAccent,
        '#f4f4f5',
    ).foreground;

    vars['--accent'] = resolvedAccent;
    vars['--accent-foreground'] = accentFg;

    const sidebarAccentSurface = isInvertedSidebar
        ? adaptAccentForDarkSurface(accentHex)
        : resolvedAccent;
    vars['--sidebar-accent'] = sidebarAccentSurface;
    vars['--sidebar-accent-foreground'] = resolveForegroundAndRatio(
        sidebarAccentSurface,
        '#f4f4f5',
    ).foreground;

    if (values.menu_accent === 'bold') {
        vars['--sidebar-accent'] = resolvedPrimary;
        vars['--sidebar-accent-foreground'] = primaryFg;
    }

    const chartOption = editorData.chart_color_options.find(
        (item) => item.key === values.chart_color,
    );
    if (chartOption) {
        Object.assign(vars, chartOption[mode]);
    }

    const bodyFontOption = editorData.font_options.find(
        (item) => item.key === values.body_font,
    );
    if (bodyFontOption) {
        vars['--font-sans'] = bodyFontOption.css_stack;
    }

    const headingFontOption = editorData.font_options.find(
        (item) => item.key === values.heading_font,
    );
    if (headingFontOption) {
        vars['--font-heading'] = headingFontOption.css_stack;
    }

    vars['--destructive'] = dark
        ? 'oklch(0.396 0.141 25.723)'
        : 'oklch(0.577 0.245 27.325)';
    vars['--destructive-foreground'] = dark
        ? 'oklch(0.637 0.237 25.331)'
        : 'oklch(0.577 0.245 27.325)';

    vars['--radius'] = values.radius;

    return vars;
}

export function encodePresetCode(values: ThemeFormValues): string {
    const compact = {
        s: values.style,
        b: values.base_color,
        t: values.theme_color,
        p: values.primary,
        a: values.accent,
        c: values.chart_color,
        hf: values.heading_font,
        bf: values.body_font,
        r: values.radius,
        ms: values.menu_style,
        ma: values.menu_accent,
    };

    const json = JSON.stringify(compact);
    const bytes = new TextEncoder().encode(json);
    let binary = '';
    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    const base64Url = btoa(binary)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/g, '');

    return `${PRESET_PREFIX}${base64Url}`;
}

export function decodePresetCode(
    rawCode: string,
    editorData: TenantThemeEditorData,
): ThemeFormValues | null {
    let trimmed = rawCode.trim();
    if (trimmed.startsWith('--preset ')) {
        trimmed = trimmed.slice(9).trim();
    }

    if (!trimmed.startsWith(PRESET_PREFIX)) {
        return null;
    }

    const payload = trimmed.slice(PRESET_PREFIX.length);
    if (!payload) {
        return null;
    }

    try {
        const base64 = payload.replace(/-/g, '+').replace(/_/g, '/');
        const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
        const binary = atob(padded);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        const parsed = JSON.parse(new TextDecoder().decode(bytes)) as Record<
            string,
            unknown
        >;

        if (
            typeof parsed.p !== 'string' ||
            typeof parsed.a !== 'string' ||
            typeof parsed.r !== 'string'
        ) {
            return null;
        }

        const hasStyle = editorData.style_options.some(
            (o) => o.key === parsed.s,
        );
        const hasBase = editorData.base_color_options.some(
            (o) => o.key === parsed.b,
        );
        const hasTheme =
            parsed.t === 'custom' ||
            editorData.theme_color_options.some((o) => o.key === parsed.t);
        const hasChart = editorData.chart_color_options.some(
            (o) => o.key === parsed.c,
        );
        const hasHeading = editorData.font_options.some(
            (o) => o.key === parsed.hf,
        );
        const hasBody = editorData.font_options.some(
            (o) => o.key === parsed.bf,
        );
        const hasMenuStyle = editorData.menu_style_options.some(
            (o) => o.key === parsed.ms,
        );
        const hasMenuAccent = editorData.menu_accent_options.some(
            (o) => o.key === parsed.ma,
        );

        return {
            style: hasStyle ? String(parsed.s) : editorData.defaults.style,
            base_color: hasBase
                ? String(parsed.b)
                : editorData.defaults.base_color,
            theme_color: hasTheme ? String(parsed.t) : 'custom',
            primary: parsed.p,
            accent: parsed.a,
            chart_color: hasChart
                ? String(parsed.c)
                : editorData.defaults.chart_color,
            heading_font: hasHeading
                ? String(parsed.hf)
                : editorData.defaults.heading_font,
            body_font: hasBody
                ? String(parsed.bf)
                : editorData.defaults.body_font,
            radius: parsed.r,
            menu_style: hasMenuStyle
                ? String(parsed.ms)
                : editorData.defaults.menu_style,
            menu_accent: hasMenuAccent
                ? String(parsed.ma)
                : editorData.defaults.menu_accent,
        };
    } catch {
        return null;
    }
}

export function buildThemeCssSnippet(
    values: ThemeFormValues,
    editorData: TenantThemeEditorData,
): string {
    const lightVars = buildPreviewCssVariables(values, editorData, false);
    const darkVars = buildPreviewCssVariables(values, editorData, true);

    const formatBlock = (vars: Record<string, string>) =>
        Object.entries(vars)
            .map(([key, value]) => `    ${key}: ${value};`)
            .join('\n');

    return `:root {\n${formatBlock(lightVars)}\n}\n\n.dark {\n${formatBlock(darkVars)}\n}`;
}

export function configToFormValues(
    config: TenantThemeConfig | null,
    defaults: TenantThemeEditorData['defaults'],
): ThemeFormValues {
    return {
        style: config?.style ?? defaults.style,
        base_color: config?.base_color ?? defaults.base_color,
        theme_color: config?.theme_color ?? defaults.theme_color,
        primary: config?.primary ?? defaults.primary,
        accent: config?.accent ?? defaults.accent,
        chart_color: config?.chart_color ?? defaults.chart_color,
        heading_font: config?.heading_font ?? defaults.heading_font,
        body_font: config?.body_font ?? defaults.body_font,
        radius: config?.radius ?? defaults.radius,
        menu_style: config?.menu_style ?? defaults.menu_style,
        menu_accent: config?.menu_accent ?? defaults.menu_accent,
    };
}
