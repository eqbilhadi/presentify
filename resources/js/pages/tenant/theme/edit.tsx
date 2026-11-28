import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    Code2,
    Copy,
    FolderOpen,
    Info,
    Moon,
    Palette,
    RotateCcw,
    Shuffle,
    SlidersHorizontal,
    Sparkles,
    Sun,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import {
    ThemePreviewCanvas,
    type PreviewUiState,
} from '@/components/tenant/theme-preview-canvas';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { usePermission } from '@/hooks/use-permission';
import {
    buildPreviewCssVariables,
    buildPreviewFontUrl,
    buildThemeCssSnippet,
    configToFormValues,
    decodePresetCode,
    encodePresetCode,
    resolveForegroundAndRatio,
    type ThemeFormValues,
} from '@/lib/tenant-theme';
import {
    reset as resetTenantThemeRoute,
    update as updateTenantThemeRoute,
} from '@/routes/tenant/theme';
import type { TenantThemeEditorData, TenantThemePreset } from '@/types';

type Props = {
    theme: TenantThemeEditorData;
};

const PREVIEW_STATE_OPTIONS: Array<{ key: PreviewUiState; label: string }> = [
    { key: 'normal', label: 'Normal' },
    { key: 'empty', label: 'Kosong' },
    { key: 'loading', label: 'Memuat' },
    { key: 'error', label: 'Error' },
];

export default function TenantThemeEdit({ theme }: Props) {
    const { can } = usePermission();
    const canUpdate = can('tenant_theme.update');

    const initialValues = useMemo(
        () => configToFormValues(theme.config, theme.defaults),
        [theme.config, theme.defaults],
    );

    const { data, setData, put, processing, errors, isDirty } =
        useForm<ThemeFormValues>(initialValues);

    const [previewDark, setPreviewDark] = useState(false);
    const [previewUiState, setPreviewUiState] =
        useState<PreviewUiState>('normal');
    const [showCustomColors, setShowCustomColors] = useState(
        initialValues.theme_color === 'custom',
    );
    const [confirmResetOpen, setConfirmResetOpen] = useState(false);
    const [resetting, setResetting] = useState(false);
    const [openPresetModal, setOpenPresetModal] = useState(false);
    const [presetCodeInput, setPresetCodeInput] = useState('');
    const [presetCodeError, setPresetCodeError] = useState<string | null>(null);
    const [getCodeModalOpen, setGetCodeModalOpen] = useState(false);
    const [codeTab, setCodeTab] = useState<'css' | 'json'>('css');
    const [copiedKey, setCopiedKey] = useState<'preset' | 'code' | null>(null);

    const primaryAnalysis = useMemo(
        () => resolveForegroundAndRatio(data.primary, theme.defaults.primary),
        [data.primary, theme.defaults.primary],
    );

    const accentAnalysis = useMemo(
        () => resolveForegroundAndRatio(data.accent, theme.defaults.accent),
        [data.accent, theme.defaults.accent],
    );

    const previewCssVariables = useMemo(
        () => buildPreviewCssVariables(data, theme, previewDark),
        [data, theme, previewDark],
    );

    const generatedPresetCode = useMemo(() => encodePresetCode(data), [data]);

    const cssSnippet = useMemo(
        () => buildThemeCssSnippet(data, theme),
        [data, theme],
    );

    const jsonSnippet = useMemo(() => JSON.stringify(data, null, 2), [data]);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        const previewFontUrl = buildPreviewFontUrl(
            theme,
            data.heading_font,
            data.body_font,
        );
        const existingLink = document.getElementById(
            'tenant-theme-preview-fonts',
        ) as HTMLLinkElement | null;

        if (previewFontUrl) {
            if (existingLink) {
                if (existingLink.href !== previewFontUrl) {
                    existingLink.href = previewFontUrl;
                }
            } else {
                const link = document.createElement('link');
                link.id = 'tenant-theme-preview-fonts';
                link.rel = 'stylesheet';
                link.href = previewFontUrl;
                document.head.appendChild(link);
            }
        } else {
            existingLink?.remove();
        }

        return () => {
            document.getElementById('tenant-theme-preview-fonts')?.remove();
        };
    }, [theme, data.heading_font, data.body_font]);

    function copyText(text: string, key: 'preset' | 'code') {
        if (typeof navigator === 'undefined' || !navigator.clipboard) {
            return;
        }

        void navigator.clipboard.writeText(text).then(() => {
            setCopiedKey(key);
            window.setTimeout(() => {
                setCopiedKey((prev) => (prev === key ? null : prev));
            }, 1800);
        });
    }

    function applyFullPreset(preset: TenantThemePreset) {
        if (!canUpdate || processing || resetting) {
            return;
        }

        setShowCustomColors(false);
        setData({
            style: preset.style,
            base_color: preset.base_color,
            theme_color: preset.theme_color,
            primary: preset.primary,
            accent: preset.accent,
            chart_color: preset.chart_color,
            heading_font: preset.heading_font,
            body_font: preset.body_font,
            radius: preset.radius,
            menu_style: preset.menu_style,
            menu_accent: preset.menu_accent,
        });
        setOpenPresetModal(false);
    }

    function handleStyleChange(styleKey: string) {
        const option = theme.style_options.find(
            (item) => item.key === styleKey,
        );
        setData((prev) => ({
            ...prev,
            style: styleKey,
            radius: option ? option.default_radius : prev.radius,
        }));
    }

    function handleThemeColorChange(colorKey: string) {
        if (colorKey === 'custom') {
            setShowCustomColors(true);
            setData('theme_color', 'custom');
            return;
        }

        const option = theme.theme_color_options.find(
            (item) => item.key === colorKey,
        );
        if (!option) {
            return;
        }

        setShowCustomColors(false);
        setData((prev) => ({
            ...prev,
            theme_color: option.key,
            primary: option.primary,
            accent: option.accent,
        }));
    }

    function handleShuffle() {
        if (!canUpdate || processing || resetting) {
            return;
        }

        const pick = <T,>(items: T[]): T =>
            items[Math.floor(Math.random() * items.length)];

        const styleOpt = pick(theme.style_options);
        const baseOpt = pick(theme.base_color_options);
        const colorOpt = pick(theme.theme_color_options);
        const chartOpt = pick(theme.chart_color_options);
        const headingOpt = pick(theme.font_options);
        const bodyOpt = pick(theme.font_options);
        const radiusOpt = pick(theme.radius_options);
        const menuStyleOpt = pick(theme.menu_style_options);
        const menuAccentOpt = pick(theme.menu_accent_options);

        setShowCustomColors(false);
        setData({
            style: styleOpt.key,
            base_color: baseOpt.key,
            theme_color: colorOpt.key,
            primary: colorOpt.primary,
            accent: colorOpt.accent,
            chart_color: chartOpt.key,
            heading_font: headingOpt.key,
            body_font: bodyOpt.key,
            radius: radiusOpt.value,
            menu_style: menuStyleOpt.key,
            menu_accent: menuAccentOpt.key,
        });
    }

    function handleApplyPresetCode() {
        const decoded = decodePresetCode(presetCodeInput, theme);
        if (!decoded) {
            setPresetCodeError(
                'Kode preset tidak valid. Pastikan format diawali prs_ atau --preset prs_...',
            );
            return;
        }

        setPresetCodeError(null);
        setShowCustomColors(decoded.theme_color === 'custom');
        setData(decoded);
        setPresetCodeInput('');
        setOpenPresetModal(false);
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (!canUpdate || processing || resetting) {
            return;
        }

        put(updateTenantThemeRoute.url(), {
            preserveScroll: true,
        });
    }

    function confirmResetTheme() {
        if (!canUpdate || resetting) {
            return;
        }

        setResetting(true);
        router.delete(resetTenantThemeRoute.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmResetOpen(false);
                setShowCustomColors(false);
                setData(theme.defaults);
            },
            onFinish: () => {
                setResetting(false);
            },
        });
    }

    const isCustomActive = theme.config !== null;

    return (
        <>
            <Head title="Tema & Tampilan Instansi" />

            <div className="mx-auto flex w-full max-w-[1440px] flex-col gap-5 px-4 py-5 sm:px-6">
                <header className="flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Tema & Tampilan Instansi
                            </h1>
                            <Badge variant="outline">
                                {isCustomActive ? 'Kustom Aktif' : 'Bawaan'}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Atur style desain, palet warna dasar, tema warna,
                            grafik, tipografi heading & body, serta gaya menu
                            sidebar instansi secara dinamis.
                        </p>
                    </div>

                    {canUpdate && isCustomActive && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="min-h-10 sm:min-h-9"
                            disabled={processing || resetting}
                            onClick={() => setConfirmResetOpen(true)}
                            data-test="open-reset-theme"
                        >
                            <RotateCcw className="size-4" aria-hidden="true" />
                            Reset Default
                        </Button>
                    )}
                </header>

                {!canUpdate && (
                    <Alert>
                        <Info className="size-4" aria-hidden="true" />
                        <AlertDescription>
                            Anda memiliki akses lihat saja. Perubahan tema
                            instansi memerlukan izin{' '}
                            <code className="font-mono text-xs">
                                tenant_theme.update
                            </code>
                            .
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-5 lg:grid-cols-[312px_1fr] lg:items-start">
                    {/* Panel Customizer Kiri — Menyatu dengan tema shell aplikasi */}
                    <form
                        onSubmit={handleSubmit}
                        className="flex flex-col gap-3 rounded-lg border bg-card p-3.5 text-card-foreground"
                    >
                        <div className="flex items-center justify-between border-b pb-2.5">
                            <span className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <Palette
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                Customizer Tema
                            </span>
                            {primaryAnalysis.isValid && (
                                <span
                                    className="font-mono text-xs text-muted-foreground tabular-nums"
                                    title="Rasio kontras warna utama terhadap teks"
                                >
                                    Kontras{' '}
                                    {primaryAnalysis.contrastRatio.toFixed(1)}:1
                                </span>
                            )}
                        </div>

                        {/* Style */}
                        <div className="space-y-1">
                            <Label
                                htmlFor="theme_style"
                                className="text-xs text-muted-foreground"
                            >
                                Style
                            </Label>
                            <Select
                                value={data.style}
                                onValueChange={handleStyleChange}
                                disabled={!canUpdate || processing}
                            >
                                <SelectTrigger
                                    id="theme_style"
                                    className="h-9 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {theme.style_options.map((opt) => (
                                        <SelectItem
                                            key={opt.key}
                                            value={opt.key}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.style} />
                        </div>

                        {/* Base Color */}
                        <div className="space-y-1">
                            <Label
                                htmlFor="theme_base_color"
                                className="text-xs text-muted-foreground"
                            >
                                Base Color
                            </Label>
                            <Select
                                value={data.base_color}
                                onValueChange={(value) =>
                                    setData('base_color', value)
                                }
                                disabled={!canUpdate || processing}
                            >
                                <SelectTrigger
                                    id="theme_base_color"
                                    className="h-9 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {theme.base_color_options.map((opt) => (
                                        <SelectItem
                                            key={opt.key}
                                            value={opt.key}
                                        >
                                            <span className="inline-flex items-center gap-2">
                                                <span
                                                    className="inline-block size-3 shrink-0 rounded-full border"
                                                    style={{
                                                        backgroundColor:
                                                            opt.swatch,
                                                    }}
                                                    aria-hidden="true"
                                                />
                                                <span>{opt.label}</span>
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.base_color} />
                        </div>

                        {/* Theme Color */}
                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between gap-2">
                                <Label
                                    htmlFor="theme_color_select"
                                    className="text-xs text-muted-foreground"
                                >
                                    Theme
                                </Label>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowCustomColors((prev) => !prev)
                                    }
                                    className="text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                                >
                                    {showCustomColors
                                        ? 'Tutup Hex/HSL'
                                        : 'Kustom Hex/HSL'}
                                </button>
                            </div>
                            <Select
                                value={data.theme_color}
                                onValueChange={handleThemeColorChange}
                                disabled={!canUpdate || processing}
                            >
                                <SelectTrigger
                                    id="theme_color_select"
                                    className="h-9 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {theme.theme_color_options.map((opt) => (
                                        <SelectItem
                                            key={opt.key}
                                            value={opt.key}
                                        >
                                            <span className="inline-flex items-center gap-2">
                                                <span
                                                    className="inline-block size-3 shrink-0 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            opt.primary,
                                                    }}
                                                    aria-hidden="true"
                                                />
                                                <span>{opt.label}</span>
                                            </span>
                                        </SelectItem>
                                    ))}
                                    <SelectItem value="custom">
                                        <span className="inline-flex items-center gap-2">
                                            <span
                                                className="inline-block size-3 shrink-0 rounded-full border"
                                                style={{
                                                    backgroundColor:
                                                        primaryAnalysis.hex,
                                                }}
                                                aria-hidden="true"
                                            />
                                            <span>Kustom (Hex / HSL)</span>
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            {showCustomColors && (
                                <div className="space-y-2.5 rounded-md border bg-muted/30 p-2.5">
                                    <div className="space-y-1">
                                        <Label
                                            htmlFor="theme_primary"
                                            className="text-xs"
                                        >
                                            Primary (--primary)
                                        </Label>
                                        <div className="flex items-center gap-1.5">
                                            <label
                                                htmlFor="theme_primary_picker"
                                                className="sr-only"
                                            >
                                                Pemilih warna utama
                                            </label>
                                            <input
                                                id="theme_primary_picker"
                                                type="color"
                                                value={primaryAnalysis.hex}
                                                disabled={
                                                    !canUpdate || processing
                                                }
                                                onChange={(e) => {
                                                    setData((prev) => ({
                                                        ...prev,
                                                        theme_color: 'custom',
                                                        primary: e.target.value,
                                                    }));
                                                }}
                                                className="h-9 w-10 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                                            />
                                            <Input
                                                id="theme_primary"
                                                value={data.primary}
                                                disabled={
                                                    !canUpdate || processing
                                                }
                                                onChange={(e) => {
                                                    setData((prev) => ({
                                                        ...prev,
                                                        theme_color: 'custom',
                                                        primary: e.target.value,
                                                    }));
                                                }}
                                                className="h-9 font-mono text-xs"
                                            />
                                        </div>
                                        <InputError message={errors.primary} />
                                    </div>

                                    <div className="space-y-1">
                                        <Label
                                            htmlFor="theme_accent"
                                            className="text-xs"
                                        >
                                            Accent (--accent)
                                        </Label>
                                        <div className="flex items-center gap-1.5">
                                            <label
                                                htmlFor="theme_accent_picker"
                                                className="sr-only"
                                            >
                                                Pemilih warna aksen
                                            </label>
                                            <input
                                                id="theme_accent_picker"
                                                type="color"
                                                value={accentAnalysis.hex}
                                                disabled={
                                                    !canUpdate || processing
                                                }
                                                onChange={(e) => {
                                                    setData((prev) => ({
                                                        ...prev,
                                                        theme_color: 'custom',
                                                        accent: e.target.value,
                                                    }));
                                                }}
                                                className="h-9 w-10 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                                            />
                                            <Input
                                                id="theme_accent"
                                                value={data.accent}
                                                disabled={
                                                    !canUpdate || processing
                                                }
                                                onChange={(e) => {
                                                    setData((prev) => ({
                                                        ...prev,
                                                        theme_color: 'custom',
                                                        accent: e.target.value,
                                                    }));
                                                }}
                                                className="h-9 font-mono text-xs"
                                            />
                                        </div>
                                        <InputError message={errors.accent} />
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Chart Color */}
                        <div className="space-y-1">
                            <Label
                                htmlFor="theme_chart_color"
                                className="text-xs text-muted-foreground"
                            >
                                Chart Color
                            </Label>
                            <Select
                                value={data.chart_color}
                                onValueChange={(value) =>
                                    setData('chart_color', value)
                                }
                                disabled={!canUpdate || processing}
                            >
                                <SelectTrigger
                                    id="theme_chart_color"
                                    className="h-9 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {theme.chart_color_options.map((opt) => (
                                        <SelectItem
                                            key={opt.key}
                                            value={opt.key}
                                        >
                                            <span className="inline-flex items-center gap-2">
                                                <span
                                                    className="inline-block size-3 shrink-0 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            opt.swatch,
                                                    }}
                                                    aria-hidden="true"
                                                />
                                                <span>{opt.label}</span>
                                            </span>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.chart_color} />
                        </div>

                        {/* Heading Font */}
                        <div className="space-y-1">
                            <div className="flex items-center justify-between gap-2">
                                <Label
                                    htmlFor="theme_heading_font"
                                    className="text-xs text-muted-foreground"
                                >
                                    Heading Font
                                </Label>
                                <span
                                    className="font-mono text-[11px] text-muted-foreground"
                                    aria-hidden="true"
                                >
                                    Aa
                                </span>
                            </div>
                            <Select
                                value={data.heading_font}
                                onValueChange={(value) =>
                                    setData('heading_font', value)
                                }
                                disabled={!canUpdate || processing}
                            >
                                <SelectTrigger
                                    id="theme_heading_font"
                                    className="h-9 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {theme.font_options.map((opt) => (
                                        <SelectItem
                                            key={opt.key}
                                            value={opt.key}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.heading_font} />
                        </div>

                        {/* Body Font */}
                        <div className="space-y-1">
                            <div className="flex items-center justify-between gap-2">
                                <Label
                                    htmlFor="theme_body_font"
                                    className="text-xs text-muted-foreground"
                                >
                                    Body Font
                                </Label>
                                <span
                                    className="font-mono text-[11px] text-muted-foreground"
                                    aria-hidden="true"
                                >
                                    Aa
                                </span>
                            </div>
                            <Select
                                value={data.body_font}
                                onValueChange={(value) =>
                                    setData('body_font', value)
                                }
                                disabled={!canUpdate || processing}
                            >
                                <SelectTrigger
                                    id="theme_body_font"
                                    className="h-9 w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {theme.font_options.map((opt) => (
                                        <SelectItem
                                            key={opt.key}
                                            value={opt.key}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.body_font} />
                        </div>

                        {/* Radius & Icon Library */}
                        <div className="grid grid-cols-2 gap-2.5">
                            <div className="space-y-1">
                                <Label
                                    htmlFor="theme_radius"
                                    className="text-xs text-muted-foreground"
                                >
                                    Radius
                                </Label>
                                <Select
                                    value={data.radius}
                                    onValueChange={(value) =>
                                        setData('radius', value)
                                    }
                                    disabled={!canUpdate || processing}
                                >
                                    <SelectTrigger
                                        id="theme_radius"
                                        className="h-9 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {theme.radius_options.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.radius} />
                            </div>

                            <div className="space-y-1">
                                <span className="block text-xs font-medium text-muted-foreground">
                                    Icon Library
                                </span>
                                <div className="flex h-9 items-center justify-between rounded-md border bg-muted/30 px-2.5 text-xs">
                                    <span className="font-medium">Lucide</span>
                                    <span className="text-[11px] text-muted-foreground">
                                        Tetap
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Menu Style & Menu Accent */}
                        <div className="grid grid-cols-2 gap-2.5">
                            <div className="space-y-1">
                                <Label
                                    htmlFor="theme_menu_style"
                                    className="text-xs text-muted-foreground"
                                >
                                    Menu
                                </Label>
                                <Select
                                    value={data.menu_style}
                                    onValueChange={(value) =>
                                        setData('menu_style', value)
                                    }
                                    disabled={!canUpdate || processing}
                                >
                                    <SelectTrigger
                                        id="theme_menu_style"
                                        className="h-9 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {theme.menu_style_options.map((opt) => (
                                            <SelectItem
                                                key={opt.key}
                                                value={opt.key}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.menu_style} />
                            </div>

                            <div className="space-y-1">
                                <Label
                                    htmlFor="theme_menu_accent"
                                    className="text-xs text-muted-foreground"
                                >
                                    Menu Accent
                                </Label>
                                <Select
                                    value={data.menu_accent}
                                    onValueChange={(value) =>
                                        setData('menu_accent', value)
                                    }
                                    disabled={!canUpdate || processing}
                                >
                                    <SelectTrigger
                                        id="theme_menu_accent"
                                        className="h-9 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {theme.menu_accent_options.map(
                                            (opt) => (
                                                <SelectItem
                                                    key={opt.key}
                                                    value={opt.key}
                                                >
                                                    {opt.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.menu_accent} />
                            </div>
                        </div>

                        {/* Preset & Utility Actions */}
                        <div className="space-y-2 border-t pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="min-h-9 w-full justify-between font-mono text-xs"
                                onClick={() =>
                                    copyText(
                                        `--preset ${generatedPresetCode}`,
                                        'preset',
                                    )
                                }
                                data-test="copy-preset-code"
                            >
                                <span className="truncate">
                                    --preset {generatedPresetCode.slice(0, 14)}
                                    ...
                                </span>
                                {copiedKey === 'preset' ? (
                                    <Check
                                        className="size-3.5 shrink-0 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <Copy
                                        className="size-3.5 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                )}
                            </Button>

                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="min-h-9 text-xs"
                                    onClick={() => {
                                        setPresetCodeError(null);
                                        setOpenPresetModal(true);
                                    }}
                                    data-test="open-preset-dialog"
                                >
                                    <FolderOpen
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    Open Preset
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!canUpdate || processing}
                                    className="min-h-9 text-xs"
                                    onClick={handleShuffle}
                                    data-test="shuffle-theme"
                                >
                                    <Shuffle
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    Shuffle
                                </Button>
                            </div>

                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                className="min-h-9 w-full text-xs"
                                onClick={() => setGetCodeModalOpen(true)}
                                data-test="get-theme-code"
                            >
                                <Code2
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                Get Code
                            </Button>
                        </div>

                        {canUpdate && (
                            <div className="border-t pt-3">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || resetting || !isDirty
                                    }
                                    className="min-h-10 w-full"
                                    data-test="save-tenant-theme"
                                >
                                    {processing && (
                                        <Spinner className="size-4" />
                                    )}
                                    Terapkan & Simpan Tema
                                </Button>
                            </div>
                        )}
                    </form>

                    {/* Kanvas Live Preview Kanan */}
                    <div className="flex flex-col gap-3">
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card px-4 py-2.5">
                            <div className="flex items-center gap-2 text-sm font-medium">
                                <SlidersHorizontal
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span>Pratinjau Langsung Komponen</span>
                                {isDirty && (
                                    <Badge variant="secondary">
                                        Belum Disimpan
                                    </Badge>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <div
                                    className="flex items-center rounded-md border bg-muted/40 p-0.5"
                                    role="group"
                                    aria-label="Simulasi kondisi tampilan"
                                >
                                    {PREVIEW_STATE_OPTIONS.map((item) => (
                                        <button
                                            key={item.key}
                                            type="button"
                                            onClick={() =>
                                                setPreviewUiState(item.key)
                                            }
                                            className={`rounded px-2.5 py-1 text-xs font-medium transition-colors ${
                                                previewUiState === item.key
                                                    ? 'bg-background text-foreground shadow-xs'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            {item.label}
                                        </button>
                                    ))}
                                </div>

                                <div
                                    className="flex items-center gap-1"
                                    role="group"
                                    aria-label="Mode warna pratinjau"
                                >
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={
                                            !previewDark ? 'default' : 'outline'
                                        }
                                        className="h-8 gap-1.5 text-xs"
                                        onClick={() => setPreviewDark(false)}
                                    >
                                        <Sun
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Terang
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={
                                            previewDark ? 'default' : 'outline'
                                        }
                                        className="h-8 gap-1.5 text-xs"
                                        onClick={() => setPreviewDark(true)}
                                    >
                                        <Moon
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Gelap
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <ThemePreviewCanvas
                            values={data}
                            cssVariables={previewCssVariables}
                            dark={previewDark}
                            uiState={previewUiState}
                            onUiStateChange={setPreviewUiState}
                        />
                    </div>
                </div>
            </div>

            {/* Dialog Open Preset */}
            <Dialog open={openPresetModal} onOpenChange={setOpenPresetModal}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Open Preset Tema</DialogTitle>
                        <DialogDescription>
                            Pilih salah satu kombinasi preset siap pakai atau
                            tempel kode{' '}
                            <code className="font-mono text-xs">--preset</code>{' '}
                            dari instansi lain.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2 rounded-lg border bg-muted/20 p-3">
                            <Label htmlFor="preset_code_input">
                                Tempel Kode Preset
                            </Label>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Input
                                    id="preset_code_input"
                                    value={presetCodeInput}
                                    onChange={(e) => {
                                        setPresetCodeInput(e.target.value);
                                        setPresetCodeError(null);
                                    }}
                                    placeholder="--preset prs_..."
                                    className="font-mono text-xs"
                                />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    disabled={
                                        !canUpdate || !presetCodeInput.trim()
                                    }
                                    onClick={handleApplyPresetCode}
                                >
                                    Terapkan Kode
                                </Button>
                            </div>
                            {presetCodeError && (
                                <p className="text-xs text-destructive">
                                    {presetCodeError}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <span className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                <Sparkles
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                Preset Kurasi Presentify
                            </span>
                            <div className="grid gap-2.5 sm:grid-cols-2">
                                {theme.presets.map((preset) => (
                                    <button
                                        key={preset.key}
                                        type="button"
                                        disabled={!canUpdate}
                                        onClick={() => applyFullPreset(preset)}
                                        data-test={`theme-preset-${preset.key}`}
                                        className="flex items-start gap-3 rounded-lg border p-3 text-left transition-colors hover:border-foreground/40 hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60"
                                    >
                                        <span
                                            className="mt-0.5 flex shrink-0 items-center gap-1"
                                            aria-hidden="true"
                                        >
                                            <span
                                                className="size-4 rounded-sm border"
                                                style={{
                                                    backgroundColor:
                                                        preset.primary,
                                                }}
                                            />
                                            <span
                                                className="size-4 rounded-sm border"
                                                style={{
                                                    backgroundColor:
                                                        preset.accent,
                                                }}
                                            />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-medium">
                                                {preset.label}
                                            </span>
                                            <span className="mt-0.5 block text-xs text-muted-foreground">
                                                {preset.description}
                                            </span>
                                            <span className="mt-1.5 block font-mono text-[11px] text-muted-foreground">
                                                {preset.style} ·{' '}
                                                {preset.heading_font} /{' '}
                                                {preset.body_font}
                                            </span>
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpenPresetModal(false)}
                        >
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Dialog Get Code */}
            <Dialog open={getCodeModalOpen} onOpenChange={setGetCodeModalOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            Ekspor Token & Konfigurasi Tema
                        </DialogTitle>
                        <DialogDescription>
                            Salin CSS variables untuk Tailwind v4 / shadcn/ui
                            atau JSON konfigurasi tema instansi.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <div className="flex items-center gap-1.5">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        codeTab === 'css'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => setCodeTab('css')}
                                >
                                    CSS Variables
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        codeTab === 'json'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => setCodeTab('json')}
                                >
                                    JSON Config
                                </Button>
                            </div>

                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                onClick={() =>
                                    copyText(
                                        codeTab === 'css'
                                            ? cssSnippet
                                            : jsonSnippet,
                                        'code',
                                    )
                                }
                            >
                                {copiedKey === 'code' ? (
                                    <Check
                                        className="size-3.5 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <Copy
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                )}
                                {copiedKey === 'code'
                                    ? 'Tersalin'
                                    : 'Salin Kode'}
                            </Button>
                        </div>

                        <pre className="max-h-80 overflow-auto rounded-lg border bg-muted/40 p-3.5 font-mono text-xs leading-relaxed">
                            <code>
                                {codeTab === 'css' ? cssSnippet : jsonSnippet}
                            </code>
                        </pre>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setGetCodeModalOpen(false)}
                        >
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Dialog Reset Konfirmasi */}
            <Dialog
                open={confirmResetOpen}
                onOpenChange={(open) => {
                    if (!open && !resetting) {
                        setConfirmResetOpen(false);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reset Tema ke Default</DialogTitle>
                        <DialogDescription>
                            Seluruh konfigurasi style, palet warna, font, menu,
                            dan sudut kustom instansi ini akan dihapus dan
                            kembali ke bawaan Presentify.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={resetting}
                            onClick={() => setConfirmResetOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={resetting}
                            onClick={confirmResetTheme}
                            data-test="confirm-reset-theme"
                        >
                            {resetting && <Spinner className="size-4" />}
                            Reset Tema
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
