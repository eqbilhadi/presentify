import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Info } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    index as tenantUsersIndexRoute,
    store as storeTenantUserRoute,
    update as updateTenantUserRoute,
} from '@/routes/tenant/rbac/users';
import type { RbacRoleOption, TenantUserDetail } from '@/types';

type Props = {
    user: TenantUserDetail | null;
    roleOptions: RbacRoleOption[];
};

export default function TenantUserForm({ user, roleOptions }: Props) {
    const isEditing = user !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        nip: user?.nip ?? '',
        position: user?.position ?? '',
        is_active: user?.is_active ?? true,
        role_ids: user?.role_ids ?? ([] as string[]),
    });

    function toggleRole(roleId: string) {
        const next = new Set(data.role_ids);
        if (next.has(roleId)) {
            next.delete(roleId);
        } else {
            next.add(roleId);
        }

        setData('role_ids', Array.from(next));
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (isEditing && user) {
            put(updateTenantUserRoute.url(user.id));
            return;
        }

        post(storeTenantUserRoute.url());
    }

    return (
        <>
            <Head
                title={
                    isEditing
                        ? 'Ubah Pengguna Instansi'
                        : 'Tambah Pengguna Instansi'
                }
            />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-3 border-b pb-5">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-2 w-fit text-muted-foreground"
                        asChild
                    >
                        <Link href={tenantUsersIndexRoute.url()}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Kembali ke Daftar Pengguna
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {isEditing
                                ? `Ubah Akses: ${user.name}`
                                : 'Tambah Pengguna Instansi'}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {isEditing
                                ? 'Atur peran lokal, identitas pegawai, dan status keanggotaan pada instansi ini.'
                                : 'Tambahkan pengguna baru atau tautkan akun Presentify yang sudah terdaftar melalui alamat email.'}
                        </p>
                    </div>
                </header>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    {!isEditing && (
                        <div className="flex items-start gap-2.5 rounded-lg border bg-muted/30 px-4 py-3 text-xs text-muted-foreground">
                            <Info
                                className="mt-0.5 size-4 shrink-0 text-foreground/70"
                                aria-hidden="true"
                            />
                            <p>
                                Jika alamat email sudah terdaftar sebagai akun
                                global Presentify, akun akan langsung ditautkan
                                ke instansi ini (kolom nama dan kata sandi boleh
                                dikosongkan).
                            </p>
                        </div>
                    )}

                    <section className="grid gap-4 rounded-lg border p-5 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="tenant-user-email">
                                Alamat Email
                            </Label>
                            <Input
                                id="tenant-user-email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                placeholder="pegawai@instansi.go.id"
                                disabled={isEditing}
                                required
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tenant-user-name">
                                Nama Lengkap
                            </Label>
                            <Input
                                id="tenant-user-name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Nama lengkap pegawai"
                                disabled={isEditing}
                            />
                            <InputError message={errors.name} />
                        </div>

                        {!isEditing && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="tenant-user-password">
                                        Kata Sandi (Akun Baru)
                                    </Label>
                                    <Input
                                        id="tenant-user-password"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                        placeholder="Wajib jika email belum terdaftar"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="tenant-user-password-confirmation">
                                        Konfirmasi Kata Sandi
                                    </Label>
                                    <Input
                                        id="tenant-user-password-confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) =>
                                            setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Ulangi kata sandi"
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>
                            </>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="tenant-user-nip">
                                NIP / ID Pegawai (Opsional)
                            </Label>
                            <Input
                                id="tenant-user-nip"
                                value={data.nip}
                                onChange={(e) => setData('nip', e.target.value)}
                                placeholder="198501012010011001"
                                className="font-mono text-sm"
                            />
                            <InputError message={errors.nip} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="tenant-user-position">
                                Jabatan / Posisi (Opsional)
                            </Label>
                            <Input
                                id="tenant-user-position"
                                value={data.position}
                                onChange={(e) =>
                                    setData('position', e.target.value)
                                }
                                placeholder="Analis Kepegawaian"
                            />
                            <InputError message={errors.position} />
                        </div>

                        <div className="sm:col-span-2">
                            <label
                                htmlFor="tenant-user-active"
                                className="flex cursor-pointer items-center gap-3 rounded-md border px-3.5 py-2.5"
                            >
                                <Checkbox
                                    id="tenant-user-active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked === true)
                                    }
                                />
                                <div className="space-y-0.5">
                                    <span className="text-sm font-medium">
                                        Keanggotaan Aktif
                                    </span>
                                    <p className="text-xs text-muted-foreground">
                                        Pengguna aktif dapat mengakses instansi
                                        ini melalui SSO.
                                    </p>
                                </div>
                            </label>
                            <InputError message={errors.is_active} />
                        </div>
                    </section>

                    <section className="flex flex-col gap-3 rounded-lg border p-5">
                        <div className="border-b pb-3">
                            <h2 className="text-base font-semibold">
                                Peran Instansi
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Pilih satu atau lebih peran yang berlaku pada
                                instansi ini.
                            </p>
                        </div>

                        {roleOptions.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">
                                Belum ada peran terdaftar pada instansi ini.
                            </p>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2">
                                {roleOptions.map((role) => {
                                    const checked = data.role_ids.includes(
                                        role.id,
                                    );

                                    return (
                                        <label
                                            key={role.id}
                                            htmlFor={`tenant-role-${role.id}`}
                                            className="flex cursor-pointer items-center gap-3 rounded-md border px-3.5 py-2.5 transition-colors hover:bg-muted/30"
                                        >
                                            <Checkbox
                                                id={`tenant-role-${role.id}`}
                                                checked={checked}
                                                onCheckedChange={() =>
                                                    toggleRole(role.id)
                                                }
                                            />
                                            <span className="font-mono text-sm font-medium">
                                                {role.name}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        )}
                        <InputError message={errors.role_ids} />
                    </section>

                    <div className="flex items-center justify-end gap-3">
                        <Button variant="outline" asChild disabled={processing}>
                            <Link href={tenantUsersIndexRoute.url()}>
                                Batal
                            </Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {isEditing
                                ? 'Simpan Perubahan'
                                : 'Tambahkan Pengguna'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
