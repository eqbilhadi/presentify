import {
    AlertCircle,
    CalendarCheck,
    CheckCircle2,
    ChevronRight,
    FileCheck2,
    Home,
    Inbox,
    LayoutDashboard,
    RotateCcw,
    ShieldCheck,
    UserCheck,
    Users,
} from 'lucide-react';
import { useState, type CSSProperties } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useTerminology } from '@/hooks/use-terminology';
import type { ThemeFormValues } from '@/lib/tenant-theme';

export type PreviewUiState = 'normal' | 'empty' | 'loading' | 'error';

type Props = {
    values: ThemeFormValues;
    cssVariables: Record<string, string>;
    dark: boolean;
    uiState: PreviewUiState;
    onUiStateChange: (state: PreviewUiState) => void;
};

const WEEKLY_SERIES = [
    { label: 'Sen', pct: 68, varName: '--chart-1' },
    { label: 'Sel', pct: 86, varName: '--chart-2' },
    { label: 'Rab', pct: 74, varName: '--chart-3' },
    { label: 'Kam', pct: 92, varName: '--chart-1' },
    { label: 'Jum', pct: 64, varName: '--chart-4' },
    { label: 'Sab', pct: 95, varName: '--chart-5' },
] as const;

const MONTHLY_SERIES = [
    { label: 'M1', pct: 80, varName: '--chart-2' },
    { label: 'M2', pct: 72, varName: '--chart-3' },
    { label: 'M3', pct: 88, varName: '--chart-1' },
    { label: 'M4', pct: 94, varName: '--chart-4' },
    { label: 'M5', pct: 76, varName: '--chart-5' },
    { label: 'M6', pct: 90, varName: '--chart-1' },
] as const;

export function ThemePreviewCanvas({
    values,
    cssVariables,
    dark,
    uiState,
    onUiStateChange,
}: Props) {
    const memberLabel = useTerminology('member', 'Pegawai');
    const unitLabel = useTerminology('unit', 'Unit Kerja');
    const leaveLabel = useTerminology('leave', 'Cuti');
    const attendanceLabel = useTerminology('attendance', 'Presensi');

    const [chartRange, setChartRange] = useState<'weekly' | 'monthly'>(
        'weekly',
    );
    const [selectedMethod, setSelectedMethod] = useState('gps_wajah');
    const [toleranceMinutes, setToleranceMinutes] = useState(15);
    const [policyNote, setPolicyNote] = useState('');
    const [requirePhoto, setRequirePhoto] = useState(true);
    const [leaveDaysInput, setLeaveDaysInput] = useState('2');
    const [leaveTypeInput, setLeaveTypeInput] = useState('tahunan');
    const [activePreviewNav, setActivePreviewNav] = useState('dashboard');
    const [simFeedback, setSimFeedback] = useState<{
        type: 'success' | 'error';
        message: string;
    } | null>(null);

    const canvasStyle = {
        ...cssVariables,
        fontFamily: cssVariables['--font-sans'],
    } as CSSProperties;

    const headingStyle = {
        fontFamily:
            cssVariables['--font-heading'] ?? cssVariables['--font-sans'],
    } as CSSProperties;

    const activeSeries =
        chartRange === 'weekly' ? WEEKLY_SERIES : MONTHLY_SERIES;
    const parsedLeaveDays = Number(leaveDaysInput);

    function handleSimulateSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (!Number.isFinite(parsedLeaveDays) || parsedLeaveDays < 1) {
            setSimFeedback({
                type: 'error',
                message: 'Durasi pengajuan harus diisi minimal 1 hari kerja.',
            });
            return;
        }

        if (policyNote.trim() === '') {
            setSimFeedback({
                type: 'error',
                message:
                    'Isi catatan alasan simulasi agar validasi formulir dapat diuji.',
            });
            return;
        }

        setSimFeedback({
            type: 'success',
            message: `Simulasi validasi berhasil (${parsedLeaveDays} hari, toleransi ${toleranceMinutes} menit).`,
        });
    }

    return (
        <div
            className={dark ? 'dark' : ''}
            data-theme-style={values.style}
            data-menu-style={values.menu_style}
            data-menu-accent={values.menu_accent}
        >
            <div
                style={canvasStyle}
                className="rounded-lg border bg-background p-4 text-foreground transition-colors sm:p-5"
            >
                {uiState === 'loading' && (
                    <div
                        className="space-y-4"
                        aria-label="Pratinjau status memuat data"
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-3 rounded-lg border bg-card p-4">
                                <Skeleton className="h-4 w-40" />
                                <Skeleton className="h-3 w-56" />
                                <Skeleton className="h-32 w-full" />
                            </div>
                            <div className="space-y-3 rounded-lg border bg-card p-4">
                                <Skeleton className="h-4 w-44" />
                                <Skeleton className="h-9 w-full" />
                                <Skeleton className="h-9 w-full" />
                                <Skeleton className="h-9 w-32" />
                            </div>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Memuat pratinjau komponen dengan warna dan sudut{' '}
                            <span className="font-mono">{values.radius}</span>.
                        </p>
                    </div>
                )}

                {uiState === 'empty' && (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed bg-card px-6 py-12 text-center">
                        <span className="inline-flex size-10 items-center justify-center rounded-md border bg-muted/40">
                            <Inbox
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </span>
                        <div className="max-w-sm space-y-1">
                            <h3
                                style={headingStyle}
                                className="text-sm font-semibold"
                            >
                                Belum Ada Data {attendanceLabel}
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Contoh tampilan empty state saat{' '}
                                {unitLabel.toLowerCase()} belum memiliki catatan{' '}
                                {attendanceLabel.toLowerCase()}.
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => onUiStateChange('normal')}
                        >
                            Kembali ke Pratinjau Normal
                        </Button>
                    </div>
                )}

                {uiState === 'error' && (
                    <div className="space-y-4">
                        <Alert variant="destructive">
                            <AlertCircle
                                className="size-4"
                                aria-hidden="true"
                            />
                            <AlertTitle>
                                Contoh Status Gagal Memuat Data
                            </AlertTitle>
                            <AlertDescription>
                                Gagal mengambil rekap{' '}
                                {attendanceLabel.toLowerCase()} instansi.
                                Periksa koneksi atau coba muat ulang halaman.
                            </AlertDescription>
                        </Alert>
                        <div className="flex justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => onUiStateChange('normal')}
                            >
                                <RotateCcw
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                Pulihkan Pratinjau Normal
                            </Button>
                        </div>
                    </div>
                )}

                {uiState === 'normal' && (
                    <div className="grid gap-4 lg:grid-cols-12">
                        {/* Kolom Kiri (7/12): Grafik Warna + Simulasi Shell Menu & Aktivitas */}
                        <div className="flex flex-col gap-4 lg:col-span-7">
                            <section className="flex flex-col gap-4 rounded-lg border bg-card p-4 text-card-foreground">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <h3
                                            style={headingStyle}
                                            className="text-sm font-semibold"
                                        >
                                            Distribusi Grafik (--chart-1 s.d.
                                            --chart-5)
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            Contoh visualisasi palet grafik{' '}
                                            {attendanceLabel.toLowerCase()}{' '}
                                            (data simulasi).
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-1 rounded-md border bg-muted/40 p-0.5">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setChartRange('weekly')
                                            }
                                            className={`rounded px-2.5 py-1 text-xs font-medium transition-colors ${
                                                chartRange === 'weekly'
                                                    ? 'bg-background text-foreground shadow-xs'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            Mingguan
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setChartRange('monthly')
                                            }
                                            className={`rounded px-2.5 py-1 text-xs font-medium transition-colors ${
                                                chartRange === 'monthly'
                                                    ? 'bg-background text-foreground shadow-xs'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            Bulanan
                                        </button>
                                    </div>
                                </div>

                                <div
                                    className="grid h-40 grid-cols-6 items-end gap-2.5 pt-2"
                                    aria-label="Grafik batang simulasi palet chart"
                                >
                                    {activeSeries.map((bar) => (
                                        <div
                                            key={bar.label}
                                            className="flex h-full flex-col items-center justify-end gap-1.5"
                                        >
                                            <span className="text-[11px] text-muted-foreground tabular-nums">
                                                {bar.pct}%
                                            </span>
                                            <div
                                                className="w-full transition-all"
                                                style={{
                                                    height: `${bar.pct}%`,
                                                    backgroundColor: `var(${bar.varName})`,
                                                    borderRadius:
                                                        'calc(var(--radius) * 0.5)',
                                                }}
                                            />
                                            <span className="text-[11px] font-medium text-muted-foreground tabular-nums">
                                                {bar.label}
                                            </span>
                                        </div>
                                    ))}
                                </div>

                                <div className="space-y-1.5 border-t pt-3">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="text-muted-foreground">
                                            Indikator Progress Primer (Contoh)
                                        </span>
                                        <span className="font-medium tabular-nums">
                                            {Math.round(
                                                (toleranceMinutes / 60) * 100,
                                            )}
                                            %
                                        </span>
                                    </div>
                                    <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full transition-all"
                                            style={{
                                                width: `${Math.round((toleranceMinutes / 60) * 100)}%`,
                                                backgroundColor:
                                                    'var(--primary)',
                                            }}
                                        />
                                    </div>
                                </div>
                            </section>

                            <div className="grid gap-4 sm:grid-cols-2">
                                {/* Pratinjau Menu Sidebar & Menu Accent */}
                                <section
                                    className="rounded-lg border p-3.5"
                                    style={{
                                        backgroundColor: 'var(--sidebar)',
                                        color: 'var(--sidebar-foreground)',
                                        borderColor: 'var(--sidebar-border)',
                                    }}
                                >
                                    <div className="mb-2 flex items-center justify-between px-2">
                                        <span className="text-[11px] font-medium opacity-75">
                                            Navigasi ({values.menu_style} /{' '}
                                            {values.menu_accent})
                                        </span>
                                    </div>
                                    <div className="space-y-1">
                                        {[
                                            {
                                                id: 'dashboard',
                                                label: 'Dashboard',
                                                icon: LayoutDashboard,
                                            },
                                            {
                                                id: 'attendance',
                                                label: attendanceLabel,
                                                icon: CalendarCheck,
                                            },
                                            {
                                                id: 'members',
                                                label: memberLabel,
                                                icon: Users,
                                            },
                                        ].map((item) => {
                                            const Icon = item.icon;
                                            const isActive =
                                                activePreviewNav === item.id;
                                            return (
                                                <button
                                                    key={item.id}
                                                    type="button"
                                                    onClick={() =>
                                                        setActivePreviewNav(
                                                            item.id,
                                                        )
                                                    }
                                                    className="flex min-h-9 w-full items-center gap-2 px-2.5 py-1.5 text-left text-xs font-medium transition-colors"
                                                    style={
                                                        isActive
                                                            ? {
                                                                  backgroundColor:
                                                                      'var(--sidebar-accent)',
                                                                  color: 'var(--sidebar-accent-foreground)',
                                                                  borderRadius:
                                                                      'calc(var(--radius) * 0.75)',
                                                              }
                                                            : {
                                                                  borderRadius:
                                                                      'calc(var(--radius) * 0.75)',
                                                              }
                                                    }
                                                >
                                                    <Icon
                                                        className="size-3.5 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    <span>{item.label}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </section>

                                {/* Pratinjau Daftar & Breadcrumb */}
                                <section className="flex flex-col justify-between gap-3 rounded-lg border bg-card p-3.5 text-card-foreground">
                                    <nav
                                        aria-label="Pratinjau breadcrumb"
                                        className="flex items-center gap-1.5 text-xs text-muted-foreground"
                                    >
                                        <Home
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <ChevronRight
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                        <span>{unitLabel}</span>
                                        <ChevronRight
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                        <span className="font-medium text-foreground">
                                            Ringkasan
                                        </span>
                                    </nav>

                                    <div className="divide-y text-xs">
                                        <div className="flex items-center justify-between py-2 first:pt-0">
                                            <span className="flex items-center gap-2">
                                                <UserCheck
                                                    className="size-3.5 text-primary"
                                                    aria-hidden="true"
                                                />
                                                Check-in Shift Pagi
                                            </span>
                                            <Badge variant="secondary">
                                                Contoh
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between py-2">
                                            <span className="flex items-center gap-2">
                                                <FileCheck2
                                                    className="size-3.5 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                Pengajuan {leaveLabel}
                                            </span>
                                            <span className="font-mono text-[11px] text-muted-foreground tabular-nums">
                                                {leaveDaysInput || '0'} hr
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between py-2 last:pb-0">
                                            <span className="flex items-center gap-2">
                                                <ShieldCheck
                                                    className="size-3.5 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                                Validasi Kebijakan
                                            </span>
                                            <span className="font-mono text-[11px] text-muted-foreground tabular-nums">
                                                {toleranceMinutes} mnt
                                            </span>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>

                        {/* Kolom Kanan (5/12): Formulir Uji Interaktif (Kontrol Penuh) */}
                        <form
                            onSubmit={handleSimulateSubmit}
                            className="flex flex-col justify-between gap-4 rounded-lg border bg-card p-4 text-card-foreground lg:col-span-5"
                        >
                            <div className="space-y-3.5">
                                <div>
                                    <h3
                                        style={headingStyle}
                                        className="text-sm font-semibold"
                                    >
                                        Uji Komponen Formulir & Fokus
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Uji kontras tombol, input, select, dan
                                        validasi pesan inline.
                                    </p>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="preview_method"
                                            className="text-xs"
                                        >
                                            Metode {attendanceLabel}
                                        </Label>
                                        <Select
                                            value={selectedMethod}
                                            onValueChange={setSelectedMethod}
                                        >
                                            <SelectTrigger
                                                id="preview_method"
                                                className="h-9 w-full text-xs"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="gps_wajah">
                                                    GPS Geofence
                                                </SelectItem>
                                                <SelectItem value="qr_kantor">
                                                    QR Lokasi Kantor
                                                </SelectItem>
                                                <SelectItem value="flexi">
                                                    Flexi Time
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="preview_leave_type"
                                            className="text-xs"
                                        >
                                            Jenis {leaveLabel}
                                        </Label>
                                        <Select
                                            value={leaveTypeInput}
                                            onValueChange={setLeaveTypeInput}
                                        >
                                            <SelectTrigger
                                                id="preview_leave_type"
                                                className="h-9 w-full text-xs"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="tahunan">
                                                    {leaveLabel} Tahunan
                                                </SelectItem>
                                                <SelectItem value="sakit">
                                                    Izin Sakit
                                                </SelectItem>
                                                <SelectItem value="dinas">
                                                    Tugas Luar
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <div className="flex items-center justify-between gap-2">
                                        <Label
                                            htmlFor="preview_tolerance"
                                            className="text-xs"
                                        >
                                            Toleransi Keterlambatan
                                        </Label>
                                        <span className="font-mono text-xs font-semibold tabular-nums">
                                            {toleranceMinutes} menit
                                        </span>
                                    </div>
                                    <input
                                        id="preview_tolerance"
                                        type="range"
                                        min={5}
                                        max={60}
                                        step={5}
                                        value={toleranceMinutes}
                                        onChange={(e) =>
                                            setToleranceMinutes(
                                                Number(e.target.value),
                                            )
                                        }
                                        className="h-2 w-full cursor-pointer accent-primary"
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label
                                        htmlFor="preview_leave_days"
                                        className="text-xs"
                                    >
                                        Durasi Pengajuan (Hari)
                                    </Label>
                                    <Input
                                        id="preview_leave_days"
                                        type="number"
                                        min={1}
                                        max={30}
                                        value={leaveDaysInput}
                                        onChange={(e) =>
                                            setLeaveDaysInput(e.target.value)
                                        }
                                        className="h-9 text-xs tabular-nums"
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label
                                        htmlFor="preview_policy_note"
                                        className="text-xs"
                                    >
                                        Catatan Simulasi
                                    </Label>
                                    <textarea
                                        id="preview_policy_note"
                                        rows={2}
                                        value={policyNote}
                                        onChange={(e) => {
                                            setPolicyNote(e.target.value);
                                            if (simFeedback) {
                                                setSimFeedback(null);
                                            }
                                        }}
                                        placeholder="Tulis catatan untuk menguji validasi formulir..."
                                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-xs shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    />
                                </div>

                                <div className="flex items-center gap-2 pt-0.5">
                                    <Checkbox
                                        id="preview_require_photo"
                                        checked={requirePhoto}
                                        onCheckedChange={(checked) =>
                                            setRequirePhoto(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="preview_require_photo"
                                        className="text-xs font-normal"
                                    >
                                        Wajibkan verifikasi foto saat{' '}
                                        {attendanceLabel.toLowerCase()}
                                    </Label>
                                </div>

                                {simFeedback && (
                                    <Alert
                                        variant={
                                            simFeedback.type === 'error'
                                                ? 'destructive'
                                                : 'default'
                                        }
                                        className="py-2"
                                    >
                                        {simFeedback.type === 'error' ? (
                                            <AlertCircle
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <CheckCircle2
                                                className="size-4 text-primary"
                                                aria-hidden="true"
                                            />
                                        )}
                                        <AlertDescription className="text-xs">
                                            {simFeedback.message}
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                                <Button
                                    type="submit"
                                    size="sm"
                                    className="flex-1"
                                >
                                    Uji Validasi Simpan
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        setPolicyNote('');
                                        setToleranceMinutes(15);
                                        setLeaveDaysInput('2');
                                        setSimFeedback(null);
                                    }}
                                >
                                    Bersihkan
                                </Button>
                            </div>
                        </form>
                    </div>
                )}
            </div>
        </div>
    );
}
