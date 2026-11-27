import { Head, router, useForm } from '@inertiajs/react';
import {
    Check,
    Info,
    Palette,
    RotateCcw,
    SlidersHorizontal,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
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
import { Spinner } from '@/components/ui/spinner';
import { usePermission } from '@/hooks/use-permission';
import {
    reset as resetTenantThemeRoute,
    update as updateTenantThemeRoute,
} from '@/routes/tenant/theme';
import type { TenantThemeEditorData, TenantThemePreset } from '@/types';

type Props = {
    theme: TenantThemeEditorData;
};

const HEX_REGEX = /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/;
const HSL_REGEX =
    /^hsl\(\s*(\d{1,3}(?:\.\d+)?)\s*(?:,|\s)\s*(\d{1,3}(?:\.\d+)?)%\s*(?:,|\s)\s*(\d{1,3}(?:\.\d+)?)%\s*\)$/i;

function hslToHex(h: number, s: number, l: number): string {
    const hue = ((h % 360) + 360) % 360;
    const sat = Math.max(0, Math.min(100, s)) / 100;
    const light = Math.max(0, Math.min(100, l)) / 100;

    const a = sat * Math.min(light, 1 - light);
    const f = (n: number) => {
        const k = (n + hue / 30) % 12;
        const color = light - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);

        return Math.round(255 * color)
            .toString(16)
            .padStart(2, '0');
    };

    return `#${f(0)}${f(8)}${f(4)}`;
}

function toHexForPicker(value: string, fallback: string): string {
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

function getRelativeLuminance(hex: string): number {
    const clean = toHexForPicker(hex, '#18181b');
    const r = parseInt(clean.slice(1, 3), 16) / 255;
    const g = parseInt(clean.slice(3, 5), 16) / 255;
    const b = parseInt(clean.slice(5, 7), 16) / 255;

    const linearize = (channel: number) =>
        channel <= 0.03928
            ? channel / 12.92
            : Math.pow((channel + 0.055) / 1.055, 2.4);

    return (
        0.2126 * linearize(r) + 0.7152 * linearize(g) + 0.0722 * linearize(b)
    );
}

function resolveForegroundAndRatio(
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

export default function TenantThemeEdit({ theme }: Props) {
    const { can } = usePermission();
    const canUpdate = can('tenant_theme.update');

    const initialPrimary = theme.config?.primary ?? theme.defaults.primary;
    const initialAccent = theme.config?.accent ?? theme.defaults.accent;
    const initialRadius = theme.config?.radius ?? theme.defaults.radius;

    const { data, setData, put, processing, errors, isDirty } = useForm({
        primary: initialPrimary,
        accent: initialAccent,
        radius: initialRadius,
    });

    const [confirmResetOpen, setConfirmResetOpen] = useState(false);
    const [resetting, setResetting] = useState(false);

    const primaryAnalysis = useMemo(
        () => resolveForegroundAndRatio(data.primary, theme.defaults.primary),
        [data.primary, theme.defaults.primary],
    );

    const accentAnalysis = useMemo(
        () => resolveForegroundAndRatio(data.accent, theme.defaults.accent),
        [data.accent, theme.defaults.accent],
    );

    const previewStyle = useMemo(
        () =>
            ({
                '--primary': primaryAnalysis.hex,
                '--primary-foreground': primaryAnalysis.foreground,
                '--accent': accentAnalysis.hex,
                '--accent-foreground': accentAnalysis.foreground,
                '--ring': primaryAnalysis.hex,
                '--radius': data.radius,
            }) as React.CSSProperties,
        [primaryAnalysis, accentAnalysis, data.radius],
    );

    function applyPreset(preset: TenantThemePreset) {
        if (!canUpdate || processing || resetting) {
            return;
        }

        setData({
            primary: preset.primary,
            accent: preset.accent,
            radius: preset.radius,
        });
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
                setData({
                    primary: theme.defaults.primary,
                    accent: theme.defaults.accent,
                    radius: theme.defaults.radius,
                });
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

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-3 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
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
                            Sesuaikan warna utama, warna aksen, dan kelengkungan
                            sudut komponen shadcn/ui secara dinamis untuk
                            seluruh halaman instansi ini.
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
                            Reset ke Default
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

                <div className="grid gap-6 lg:grid-cols-[1fr_360px] lg:items-start">
                    <form
                        onSubmit={handleSubmit}
                        className="flex flex-col gap-6 rounded-lg border p-5 sm:p-6"
                    >
                        <section className="space-y-3">
                            <div className="flex items-center gap-2">
                                <Palette
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <h2 className="text-base font-semibold">
                                    Palet Cepat Instansi
                                </h2>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Pilih kombinasi awal lalu sesuaikan warna atau
                                radius sesuai pedoman identitas instansi Anda.
                            </p>

                            <div className="grid gap-2.5 sm:grid-cols-2">
                                {theme.presets.map((preset) => {
                                    const selected =
                                        data.primary.toLowerCase() ===
                                            preset.primary.toLowerCase() &&
                                        data.accent.toLowerCase() ===
                                            preset.accent.toLowerCase() &&
                                        data.radius === preset.radius;

                                    return (
                                        <button
                                            key={preset.key}
                                            type="button"
                                            aria-pressed={selected}
                                            disabled={!canUpdate || processing}
                                            onClick={() => applyPreset(preset)}
                                            data-test={`theme-preset-${preset.key}`}
                                            className={`flex min-h-11 items-start gap-3 rounded-md border p-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60 ${
                                                selected
                                                    ? 'border-foreground bg-muted/40'
                                                    : 'hover:border-foreground/35'
                                            }`}
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
                                                <span className="flex items-center justify-between gap-2 text-sm font-medium">
                                                    <span>{preset.label}</span>
                                                    {selected && (
                                                        <Check
                                                            className="size-3.5 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                </span>
                                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                                    {preset.description}
                                                </span>
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        </section>

                        <section className="space-y-4 border-t pt-5">
                            <div className="flex items-center gap-2">
                                <SlidersHorizontal
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <h2 className="text-base font-semibold">
                                    Token Warna & Sudut CSS
                                </h2>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between gap-2">
                                        <Label htmlFor="theme_primary">
                                            Warna Utama (--primary)
                                        </Label>
                                        {primaryAnalysis.isValid && (
                                            <span className="font-mono text-xs text-muted-foreground tabular-nums">
                                                Kontras{' '}
                                                {primaryAnalysis.contrastRatio.toFixed(
                                                    1,
                                                )}
                                                :1
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
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
                                            disabled={!canUpdate || processing}
                                            onChange={(e) =>
                                                setData(
                                                    'primary',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-9 w-11 shrink-0 cursor-pointer rounded-md border bg-transparent p-1 disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                        <Input
                                            id="theme_primary"
                                            value={data.primary}
                                            onChange={(e) =>
                                                setData(
                                                    'primary',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="#0f766e atau hsl(173 80% 26%)"
                                            className="font-mono text-sm"
                                            disabled={!canUpdate || processing}
                                            required
                                        />
                                    </div>
                                    <InputError message={errors.primary} />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between gap-2">
                                        <Label htmlFor="theme_accent">
                                            Warna Aksen (--accent)
                                        </Label>
                                        {accentAnalysis.isValid && (
                                            <span className="font-mono text-xs text-muted-foreground tabular-nums">
                                                Kontras{' '}
                                                {accentAnalysis.contrastRatio.toFixed(
                                                    1,
                                                )}
                                                :1
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
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
                                            disabled={!canUpdate || processing}
                                            onChange={(e) =>
                                                setData(
                                                    'accent',
                                                    e.target.value,
                                                )
                                            }
                                            className="h-9 w-11 shrink-0 cursor-pointer rounded-md border bg-transparent p-1 disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                        <Input
                                            id="theme_accent"
                                            value={data.accent}
                                            onChange={(e) =>
                                                setData(
                                                    'accent',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="#f0fdfa atau hsl(166 76% 97%)"
                                            className="font-mono text-sm"
                                            disabled={!canUpdate || processing}
                                            required
                                        />
                                    </div>
                                    <InputError message={errors.accent} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label>Kelengkungan Sudut (--radius)</Label>
                                <div className="flex flex-wrap gap-2">
                                    {theme.radius_options.map((option) => {
                                        const active =
                                            data.radius === option.value;

                                        return (
                                            <button
                                                key={option.value}
                                                type="button"
                                                aria-pressed={active}
                                                disabled={
                                                    !canUpdate || processing
                                                }
                                                onClick={() =>
                                                    setData(
                                                        'radius',
                                                        option.value,
                                                    )
                                                }
                                                data-test={`theme-radius-${option.value}`}
                                                className={`inline-flex min-h-10 items-center gap-2 border px-3 py-1.5 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60 sm:min-h-9 ${
                                                    active
                                                        ? 'border-foreground bg-foreground text-background'
                                                        : 'bg-background hover:bg-muted/50'
                                                }`}
                                                style={{
                                                    borderRadius: option.value,
                                                }}
                                            >
                                                <span className="tabular-nums">
                                                    {option.label}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                                <InputError message={errors.radius} />
                            </div>
                        </section>

                        {canUpdate && (
                            <div className="flex items-center justify-end gap-3 border-t pt-4">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || resetting || !isDirty
                                    }
                                    className="min-h-10 sm:min-h-9"
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

                    <aside className="flex flex-col gap-4 rounded-lg border p-5">
                        <div>
                            <h2 className="text-base font-semibold">
                                Pratinjau Komponen Langsung
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Simulasi tombol, menu aktif sidebar, badge, dan
                                fokus input sebelum disimpan.
                            </p>
                        </div>

                        <div
                            style={previewStyle}
                            className="flex flex-col gap-4 rounded-lg border bg-background p-4 text-foreground"
                        >
                            <div
                                className="flex items-center justify-between px-3 py-2 text-sm font-medium"
                                style={{
                                    backgroundColor: 'var(--accent)',
                                    color: 'var(--accent-foreground)',
                                    borderRadius: 'var(--radius)',
                                }}
                            >
                                <span>Menu Sidebar Aktif</span>
                                <span
                                    className="px-2 py-0.5 text-xs font-medium"
                                    style={{
                                        backgroundColor: 'var(--primary)',
                                        color: 'var(--primary-foreground)',
                                        borderRadius:
                                            'calc(var(--radius) - 2px)',
                                    }}
                                >
                                    Aktif
                                </span>
                            </div>

                            <div className="space-y-1.5">
                                <span className="block text-xs font-medium text-muted-foreground">
                                    Contoh Input Terfokus
                                </span>
                                <div
                                    className="border bg-background px-3 py-2 text-sm ring-2"
                                    style={{
                                        borderColor: 'var(--primary)',
                                        borderRadius: 'var(--radius)',
                                        boxShadow:
                                            '0 0 0 2px color-mix(in srgb, var(--ring) 25%, transparent)',
                                    }}
                                >
                                    NIP 199402122020121004
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-2 pt-1">
                                <span
                                    className="inline-flex h-9 items-center px-4 text-sm font-medium"
                                    style={{
                                        backgroundColor: 'var(--primary)',
                                        color: 'var(--primary-foreground)',
                                        borderRadius: 'var(--radius)',
                                    }}
                                >
                                    Simpan Data
                                </span>
                                <span
                                    className="inline-flex h-9 items-center border px-4 text-sm font-medium"
                                    style={{
                                        backgroundColor: 'var(--accent)',
                                        color: 'var(--accent-foreground)',
                                        borderRadius: 'var(--radius)',
                                    }}
                                >
                                    Aksi Sekunder
                                </span>
                            </div>
                        </div>

                        <div className="rounded-md border bg-muted/20 p-3 font-mono text-xs leading-relaxed text-muted-foreground">
                            <div>--primary: {primaryAnalysis.hex};</div>
                            <div>
                                --primary-foreground:{' '}
                                {primaryAnalysis.foreground};
                            </div>
                            <div>--accent: {accentAnalysis.hex};</div>
                            <div>--radius: {data.radius};</div>
                        </div>
                    </aside>
                </div>
            </div>

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
                            Seluruh konfigurasi warna dan sudut kustom instansi
                            ini akan dihapus dan kembali ke palet bawaan
                            Presentify.
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
