import { Head, router, useForm } from '@inertiajs/react';
import { BookOpenCheck, Check, Info, Languages, RotateCcw } from 'lucide-react';
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
    reset as resetTerminologyRoute,
    update as updateTerminologyRoute,
} from '@/routes/tenant/terminology';
import type { TenantTerminologyEditorData, TerminologyPreset } from '@/types';

type Props = {
    terminology: TenantTerminologyEditorData;
};

export default function TenantTerminologyEdit({ terminology }: Props) {
    const { can } = usePermission();
    const canUpdate = can('tenant_terminology.update');

    const { data, setData, put, processing, errors, isDirty } = useForm<{
        terms: Record<string, string>;
    }>({
        terms: { ...terminology.terms },
    });

    const [confirmResetOpen, setConfirmResetOpen] = useState(false);
    const [resetting, setResetting] = useState(false);

    const previewSamples = useMemo(() => {
        const term = (key: string, fallback: string) => {
            const val = data.terms[key]?.trim();

            return val && val !== '' ? val : fallback;
        };

        return [
            {
                context: 'Direktori & Struktur',
                sentence: `Daftar ${term('employee', 'Pegawai')} berdasarkan ${term('unit', 'Unit Kerja')} pada ${term('office', 'Instansi')}.`,
            },
            {
                context: 'Identitas Anggota',
                sentence: `Masukkan ${term('nip', 'NIP')} atau nama ${term('employee', 'Pegawai')} untuk mencari data.`,
            },
            {
                context: 'Pencatatan Kehadiran',
                sentence: `${term('employee', 'Pegawai')} melakukan ${term('attendance', 'Presensi')} harian sesuai jadwal ${term('unit', 'Unit Kerja')}.`,
            },
            {
                context: 'Alur Persetujuan',
                sentence: `Pengajuan ${term('leave', 'Izin & Cuti')} menunggu persetujuan ${term('supervisor', 'Atasan')} atau ${term('delegation', 'Pelaksana Tugas')}.`,
            },
        ];
    }, [data.terms]);

    function applyPreset(preset: TerminologyPreset) {
        if (!canUpdate || processing || resetting) {
            return;
        }

        setData('terms', {
            ...data.terms,
            ...preset.terms,
        });
    }

    function updateTerm(key: string, value: string) {
        setData('terms', {
            ...data.terms,
            [key]: value,
        });
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (!canUpdate || processing || resetting) {
            return;
        }

        put(updateTerminologyRoute.url(), {
            preserveScroll: true,
        });
    }

    function confirmReset() {
        if (!canUpdate || resetting) {
            return;
        }

        setResetting(true);
        router.delete(resetTerminologyRoute.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmResetOpen(false);
                setData('terms', { ...terminology.defaults });
            },
            onFinish: () => {
                setResetting(false);
            },
        });
    }

    const errorMap = errors as Record<string, string | undefined>;

    return (
        <>
            <Head title="Kamus Terminologi Instansi" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-3 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Kamus Terminologi Instansi
                            </h1>
                            <Badge variant="outline">
                                {terminology.is_customized
                                    ? 'Kustom Aktif'
                                    : 'Istilah Bawaan'}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Sesuaikan penamaan entitas seperti pegawai, unit
                            kerja, atau atasan agar selaras dengan istilah resmi
                            di lingkungan instansi Anda.
                        </p>
                    </div>

                    {canUpdate && terminology.is_customized && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="min-h-10 sm:min-h-9"
                            disabled={processing || resetting}
                            onClick={() => setConfirmResetOpen(true)}
                            data-test="open-reset-terminology"
                        >
                            <RotateCcw className="size-4" aria-hidden="true" />
                            Reset ke Bawaan
                        </Button>
                    )}
                </header>

                {!canUpdate && (
                    <Alert>
                        <Info className="size-4" aria-hidden="true" />
                        <AlertDescription>
                            Anda memiliki akses lihat saja. Perubahan kamus
                            terminologi memerlukan izin{' '}
                            <code className="font-mono text-xs">
                                tenant_terminology.update
                            </code>
                            .
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[1fr_340px] lg:items-start">
                    <form
                        onSubmit={handleSubmit}
                        className="flex flex-col gap-6 rounded-lg border p-5 sm:p-6"
                    >
                        <section className="space-y-3">
                            <div className="flex items-center gap-2">
                                <Languages
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <h2 className="text-base font-semibold">
                                    Template Istilah Sektor
                                </h2>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Terapkan sekumpulan istilah standar sesuai jenis
                                organisasi, lalu ubah masing-masing istilah bila
                                perlu.
                            </p>

                            <div className="grid gap-2.5 sm:grid-cols-2">
                                {terminology.presets.map((preset) => {
                                    const selected =
                                        terminology.definitions.every(
                                            (def) =>
                                                (
                                                    data.terms[def.key] ?? ''
                                                ).trim() ===
                                                (preset.terms[def.key] ?? ''),
                                        );

                                    return (
                                        <button
                                            key={preset.key}
                                            type="button"
                                            aria-pressed={selected}
                                            disabled={!canUpdate || processing}
                                            onClick={() => applyPreset(preset)}
                                            data-test={`terminology-preset-${preset.key}`}
                                            className={`flex min-h-11 flex-col gap-1 rounded-md border p-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-60 ${
                                                selected
                                                    ? 'border-foreground bg-muted/40'
                                                    : 'hover:border-foreground/35'
                                            }`}
                                        >
                                            <span className="flex items-center justify-between gap-2 text-sm font-medium">
                                                <span>{preset.label}</span>
                                                {selected && (
                                                    <Check
                                                        className="size-3.5 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {preset.description}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        </section>

                        <section className="space-y-4 border-t pt-5">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Daftar Pemetaan Istilah
                                </h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Setiap kunci terhubung langsung dengan hook{' '}
                                    <code className="font-mono">
                                        useTerminology()
                                    </code>{' '}
                                    di seluruh modul aplikasi.
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                {terminology.definitions.map((definition) => {
                                    const fieldError =
                                        errorMap[`terms.${definition.key}`];

                                    return (
                                        <div
                                            key={definition.key}
                                            className="grid gap-1.5 rounded-md border bg-muted/15 p-3.5"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <Label
                                                    htmlFor={`term_${definition.key}`}
                                                >
                                                    {definition.label}
                                                </Label>
                                                <code className="font-mono text-xs text-muted-foreground">
                                                    {definition.key}
                                                </code>
                                            </div>

                                            <Input
                                                id={`term_${definition.key}`}
                                                value={
                                                    data.terms[
                                                        definition.key
                                                    ] ?? ''
                                                }
                                                onChange={(e) =>
                                                    updateTerm(
                                                        definition.key,
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder={
                                                    definition.placeholder
                                                }
                                                maxLength={60}
                                                disabled={
                                                    !canUpdate || processing
                                                }
                                                required
                                            />

                                            <p className="text-xs text-muted-foreground">
                                                {definition.description}{' '}
                                                (Bawaan:{' '}
                                                <span className="font-medium text-foreground">
                                                    {definition.default}
                                                </span>
                                                )
                                            </p>

                                            <InputError message={fieldError} />
                                        </div>
                                    );
                                })}
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
                                    data-test="save-tenant-terminology"
                                >
                                    {processing && (
                                        <Spinner className="size-4" />
                                    )}
                                    Simpan Kamus Terminologi
                                </Button>
                            </div>
                        )}
                    </form>

                    <aside className="flex flex-col gap-4 rounded-lg border p-5">
                        <div className="flex items-center gap-2">
                            <BookOpenCheck
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div>
                                <h2 className="text-base font-semibold">
                                    Pratinjau Kalimat Antarmuka
                                </h2>
                            </div>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Simulasi bagaimana istilah yang Anda tentukan muncul
                            pada label modul organisasi, presensi, dan
                            persetujuan.
                        </p>

                        <ul className="flex flex-col gap-3" role="list">
                            {previewSamples.map((sample) => (
                                <li
                                    key={sample.context}
                                    className="rounded-md border bg-muted/20 p-3"
                                >
                                    <p className="text-xs font-medium text-muted-foreground">
                                        {sample.context}
                                    </p>
                                    <p className="mt-1 text-sm leading-snug">
                                        {sample.sentence}
                                    </p>
                                </li>
                            ))}
                        </ul>
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
                        <DialogTitle>Reset Kamus ke Bawaan</DialogTitle>
                        <DialogDescription>
                            Seluruh istilah khusus pada instansi ini akan
                            dihapus dan dikembalikan ke kamus default
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
                            onClick={confirmReset}
                            data-test="confirm-reset-terminology"
                        >
                            {resetting && <Spinner className="size-4" />}
                            Reset Kamus
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
