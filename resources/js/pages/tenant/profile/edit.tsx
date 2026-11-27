import { Head, router, useForm } from '@inertiajs/react';
import {
    Building2,
    Globe,
    ImagePlus,
    Info,
    Mail,
    MapPin,
    Phone,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
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
import { update as updateTenantProfileRoute } from '@/routes/tenant/profile';
import {
    destroy as destroyBrandingRoute,
    upload as uploadBrandingRoute,
} from '@/routes/tenant/profile/branding';
import type { BrandingAssetType, TenantProfile } from '@/types';

type Props = {
    profile: TenantProfile;
};

type BrandingUploadState = {
    uploadingType: BrandingAssetType | null;
    deletingType: BrandingAssetType | null;
    confirmDeleteType: BrandingAssetType | null;
    uploadErrors: Partial<Record<BrandingAssetType, string>>;
};

export default function TenantProfileEdit({ profile }: Props) {
    const { can } = usePermission();
    const canUpdate = can('tenant_profile.update');

    const logoInputRef = useRef<HTMLInputElement>(null);
    const faviconInputRef = useRef<HTMLInputElement>(null);

    const { data, setData, put, processing, errors, isDirty } = useForm({
        name: profile.name,
        contact_email: profile.contact_email ?? '',
        phone: profile.phone ?? '',
        address: profile.address ?? '',
        website: profile.website ?? '',
        tagline: profile.tagline ?? '',
    });

    const [brandingState, setBrandingState] = useState<BrandingUploadState>({
        uploadingType: null,
        deletingType: null,
        confirmDeleteType: null,
        uploadErrors: {},
    });

    function handleProfileSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (!canUpdate || processing) {
            return;
        }

        put(updateTenantProfileRoute.url(), {
            preserveScroll: true,
        });
    }

    function handleBrandingFileChange(
        type: BrandingAssetType,
        event: React.ChangeEvent<HTMLInputElement>,
    ) {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file || !canUpdate || brandingState.uploadingType !== null) {
            return;
        }

        setBrandingState((prev) => ({
            ...prev,
            uploadingType: type,
            uploadErrors: { ...prev.uploadErrors, [type]: undefined },
        }));

        router.post(
            uploadBrandingRoute.url(),
            {
                type,
                file,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (formErrors) => {
                    const message =
                        typeof formErrors.file === 'string'
                            ? formErrors.file
                            : typeof formErrors.type === 'string'
                              ? formErrors.type
                              : 'Gagal mengunggah berkas gambar.';

                    setBrandingState((prev) => ({
                        ...prev,
                        uploadErrors: {
                            ...prev.uploadErrors,
                            [type]: message,
                        },
                    }));
                },
                onFinish: () => {
                    setBrandingState((prev) => ({
                        ...prev,
                        uploadingType: null,
                    }));
                },
            },
        );
    }

    function confirmDeleteBranding() {
        const targetType = brandingState.confirmDeleteType;

        if (!targetType || !canUpdate || brandingState.deletingType !== null) {
            return;
        }

        setBrandingState((prev) => ({
            ...prev,
            deletingType: targetType,
        }));

        router.delete(destroyBrandingRoute.url(targetType), {
            preserveScroll: true,
            onSuccess: () => {
                setBrandingState((prev) => ({
                    ...prev,
                    confirmDeleteType: null,
                    uploadErrors: {
                        ...prev.uploadErrors,
                        [targetType]: undefined,
                    },
                }));
            },
            onFinish: () => {
                setBrandingState((prev) => ({
                    ...prev,
                    deletingType: null,
                }));
            },
        });
    }

    return (
        <>
            <Head title="Profil & Identitas Instansi" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-3 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Profil & Identitas Instansi
                            </h1>
                            <Badge
                                variant="outline"
                                className="font-mono text-xs"
                            >
                                {profile.slug}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Kelola identitas resmi, informasi kontak, logo
                            sidebar, dan favicon tab browser untuk instansi ini.
                        </p>
                    </div>
                </header>

                {!canUpdate && (
                    <Alert>
                        <Info className="size-4" aria-hidden="true" />
                        <AlertDescription>
                            Anda memiliki akses lihat saja. Perubahan profil dan
                            aset branding memerlukan izin{' '}
                            <code className="font-mono text-xs">
                                tenant_profile.update
                            </code>
                            .
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[1fr_340px] lg:items-start">
                    <form
                        onSubmit={handleProfileSubmit}
                        className="flex flex-col gap-5 rounded-lg border p-5 sm:p-6"
                    >
                        <div className="border-b pb-4">
                            <h2 className="text-base font-semibold">
                                Informasi Dasar Instansi
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Nama instansi ditampilkan di header sidebar dan
                                pemilih ruang kerja pengguna.
                            </p>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="tenant_name">
                                    Nama Instansi
                                </Label>
                                <Input
                                    id="tenant_name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="Contoh: Dinas Kependudukan dan Pencatatan Sipil"
                                    disabled={!canUpdate || processing}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tenant_slug">
                                    Identitas Subdomain
                                </Label>
                                <Input
                                    id="tenant_slug"
                                    value={profile.slug}
                                    readOnly
                                    disabled
                                    className="font-mono text-sm"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Slug bersifat permanen karena terikat pada
                                    subdomain dan isolasi database.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tenant_tagline">
                                    Slogan / Tagline
                                </Label>
                                <Input
                                    id="tenant_tagline"
                                    value={data.tagline}
                                    onChange={(e) =>
                                        setData('tagline', e.target.value)
                                    }
                                    placeholder="Contoh: Melayani Cepat dan Akuntabel"
                                    disabled={!canUpdate || processing}
                                />
                                <InputError message={errors.tagline} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="tenant_email">
                                    Email Kontak Resmi
                                </Label>
                                <div className="relative">
                                    <Mail
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="tenant_email"
                                        type="email"
                                        value={data.contact_email}
                                        onChange={(e) =>
                                            setData(
                                                'contact_email',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="humas@instansi.go.id"
                                        className="pl-9"
                                        disabled={!canUpdate || processing}
                                    />
                                </div>
                                <InputError message={errors.contact_email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tenant_phone">
                                    Nomor Telepon
                                </Label>
                                <div className="relative">
                                    <Phone
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="tenant_phone"
                                        type="tel"
                                        value={data.phone}
                                        onChange={(e) =>
                                            setData('phone', e.target.value)
                                        }
                                        placeholder="021-5550199"
                                        className="pl-9 tabular-nums"
                                        disabled={!canUpdate || processing}
                                    />
                                </div>
                                <InputError message={errors.phone} />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="tenant_website">
                                    Situs Web Resmi
                                </Label>
                                <div className="relative">
                                    <Globe
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="tenant_website"
                                        type="url"
                                        value={data.website}
                                        onChange={(e) =>
                                            setData('website', e.target.value)
                                        }
                                        placeholder="https://instansi.go.id"
                                        className="pl-9"
                                        disabled={!canUpdate || processing}
                                    />
                                </div>
                                <InputError message={errors.website} />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="tenant_address">
                                    Alamat Kantor
                                </Label>
                                <div className="relative">
                                    <MapPin
                                        className="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <textarea
                                        id="tenant_address"
                                        rows={3}
                                        value={data.address}
                                        onChange={(e) =>
                                            setData('address', e.target.value)
                                        }
                                        placeholder="Jalan, nomor gedung, kota, dan kode pos"
                                        disabled={!canUpdate || processing}
                                        className="flex min-h-20 w-full rounded-md border border-input bg-transparent py-2 pr-3 pl-9 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    />
                                </div>
                                <InputError message={errors.address} />
                            </div>
                        </div>

                        {profile.domains.length > 0 && (
                            <div className="rounded-md border bg-muted/20 px-4 py-3">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Domain Terhubung (
                                    <span className="tabular-nums">
                                        {profile.domains.length}
                                    </span>
                                    )
                                </p>
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {profile.domains.map((domain) => (
                                        <Badge
                                            key={domain}
                                            variant="secondary"
                                            className="font-mono text-xs"
                                        >
                                            {domain}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        {canUpdate && (
                            <div className="flex items-center justify-end gap-3 border-t pt-4">
                                <Button
                                    type="submit"
                                    disabled={processing || !isDirty}
                                    className="min-h-10 sm:min-h-9"
                                    data-test="save-tenant-profile"
                                >
                                    {processing && (
                                        <Spinner className="size-4" />
                                    )}
                                    Simpan Perubahan Profil
                                </Button>
                            </div>
                        )}
                    </form>

                    <aside className="flex flex-col gap-5">
                        <section className="flex flex-col gap-4 rounded-lg border p-5">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Logo Instansi
                                </h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Ditampilkan di sidebar dan dikompresi
                                    otomatis hingga maksimal 512 x 512 px.
                                </p>
                            </div>

                            {profile.logo_url ? (
                                <div className="flex items-center gap-4 rounded-md border bg-muted/20 p-3">
                                    <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-background">
                                        <img
                                            src={profile.logo_url}
                                            alt={`Logo ${profile.name}`}
                                            width={64}
                                            height={64}
                                            className="size-full object-contain p-1"
                                        />
                                    </div>
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <p className="text-sm font-medium">
                                            Logo aktif terpasang
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Format PNG transparan. Siap diganti
                                            kapan saja.
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed bg-muted/15 px-4 py-6 text-center">
                                    <div className="flex size-11 items-center justify-center rounded-md border bg-background text-muted-foreground">
                                        <Building2
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <div className="space-y-0.5">
                                        <p className="text-sm font-medium">
                                            Belum ada logo instansi
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Unggah PNG, JPG, atau WebP (maks. 2
                                            MB) untuk mengganti ikon bawaan.
                                        </p>
                                    </div>
                                </div>
                            )}

                            <InputError
                                message={brandingState.uploadErrors.logo}
                            />

                            {canUpdate && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <input
                                        ref={logoInputRef}
                                        id="tenant_logo_input"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={(e) =>
                                            handleBrandingFileChange('logo', e)
                                        }
                                        className="sr-only"
                                        disabled={
                                            brandingState.uploadingType !== null
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="min-h-10 flex-1 sm:min-h-9"
                                        disabled={
                                            brandingState.uploadingType !== null
                                        }
                                        onClick={() =>
                                            logoInputRef.current?.click()
                                        }
                                        data-test="upload-tenant-logo"
                                    >
                                        {brandingState.uploadingType ===
                                        'logo' ? (
                                            <Spinner className="size-4" />
                                        ) : profile.logo_url ? (
                                            <Upload
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <ImagePlus
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        )}
                                        {profile.logo_url
                                            ? 'Ganti Logo'
                                            : 'Unggah Logo'}
                                    </Button>

                                    {profile.logo_url && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="min-h-10 text-destructive hover:text-destructive sm:min-h-9"
                                            aria-label="Hapus logo instansi"
                                            disabled={
                                                brandingState.uploadingType !==
                                                    null ||
                                                brandingState.deletingType !==
                                                    null
                                            }
                                            onClick={() =>
                                                setBrandingState((prev) => ({
                                                    ...prev,
                                                    confirmDeleteType: 'logo',
                                                }))
                                            }
                                        >
                                            <Trash2
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    )}
                                </div>
                            )}
                        </section>

                        <section className="flex flex-col gap-4 rounded-lg border p-5">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Favicon Browser
                                </h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Ikon kecil di tab browser untuk domain
                                    instansi ini (maks. 128 x 128 px).
                                </p>
                            </div>

                            {profile.favicon_url ? (
                                <div className="flex items-center gap-4 rounded-md border bg-muted/20 p-3">
                                    <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-background">
                                        <img
                                            src={profile.favicon_url}
                                            alt={`Favicon ${profile.name}`}
                                            width={48}
                                            height={48}
                                            className="size-8 object-contain"
                                        />
                                    </div>
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <p className="text-sm font-medium">
                                            Favicon kustom aktif
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Otomatis diterapkan pada portal
                                            instansi.
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed bg-muted/15 px-4 py-5 text-center">
                                    <p className="text-sm font-medium">
                                        Menggunakan favicon default
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Unggah PNG, WebP, atau ICO (maks. 1 MB)
                                        untuk ikon tab khusus.
                                    </p>
                                </div>
                            )}

                            <InputError
                                message={brandingState.uploadErrors.favicon}
                            />

                            {canUpdate && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <input
                                        ref={faviconInputRef}
                                        id="tenant_favicon_input"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp,image/x-icon,.ico"
                                        onChange={(e) =>
                                            handleBrandingFileChange(
                                                'favicon',
                                                e,
                                            )
                                        }
                                        className="sr-only"
                                        disabled={
                                            brandingState.uploadingType !== null
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="min-h-10 flex-1 sm:min-h-9"
                                        disabled={
                                            brandingState.uploadingType !== null
                                        }
                                        onClick={() =>
                                            faviconInputRef.current?.click()
                                        }
                                        data-test="upload-tenant-favicon"
                                    >
                                        {brandingState.uploadingType ===
                                        'favicon' ? (
                                            <Spinner className="size-4" />
                                        ) : (
                                            <Upload
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        )}
                                        {profile.favicon_url
                                            ? 'Ganti Favicon'
                                            : 'Unggah Favicon'}
                                    </Button>

                                    {profile.favicon_url && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="min-h-10 text-destructive hover:text-destructive sm:min-h-9"
                                            aria-label="Hapus favicon instansi"
                                            disabled={
                                                brandingState.uploadingType !==
                                                    null ||
                                                brandingState.deletingType !==
                                                    null
                                            }
                                            onClick={() =>
                                                setBrandingState((prev) => ({
                                                    ...prev,
                                                    confirmDeleteType:
                                                        'favicon',
                                                }))
                                            }
                                        >
                                            <Trash2
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    )}
                                </div>
                            )}
                        </section>
                    </aside>
                </div>
            </div>

            <Dialog
                open={brandingState.confirmDeleteType !== null}
                onOpenChange={(open) => {
                    if (!open && brandingState.deletingType === null) {
                        setBrandingState((prev) => ({
                            ...prev,
                            confirmDeleteType: null,
                        }));
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {brandingState.confirmDeleteType === 'favicon'
                                ? 'Hapus Favicon Instansi'
                                : 'Hapus Logo Instansi'}
                        </DialogTitle>
                        <DialogDescription>
                            Aset gambar saat ini akan dihapus permanen dari
                            penyimpanan instansi dan tampilan akan kembali ke
                            ikon bawaan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={brandingState.deletingType !== null}
                            onClick={() =>
                                setBrandingState((prev) => ({
                                    ...prev,
                                    confirmDeleteType: null,
                                }))
                            }
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={brandingState.deletingType !== null}
                            onClick={confirmDeleteBranding}
                            data-test="confirm-delete-branding"
                        >
                            {brandingState.deletingType !== null && (
                                <Spinner className="size-4" />
                            )}
                            Hapus Aset
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
