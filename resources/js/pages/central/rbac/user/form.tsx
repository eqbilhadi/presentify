import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    index as centralUsersIndexRoute,
    store as storeCentralUserRoute,
    update as updateCentralUserRoute,
} from '@/routes/central/rbac/users';
import type { CentralUserDetail, RbacRoleOption } from '@/types';

type Props = {
    user: CentralUserDetail | null;
    roleOptions: RbacRoleOption[];
};

export default function CentralUserForm({ user, roleOptions }: Props) {
    const isEditing = user !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
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
            put(updateCentralUserRoute.url(user.id));
            return;
        }

        post(storeCentralUserRoute.url());
    }

    return (
        <>
            <Head
                title={
                    isEditing
                        ? 'Ubah Pengguna Central'
                        : 'Tambah Pengguna Central'
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
                        <Link href={centralUsersIndexRoute.url()}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Kembali ke Daftar Pengguna
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {isEditing
                                ? `Ubah Pengguna: ${user.name}`
                                : 'Tambah Pengguna Central'}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {isEditing
                                ? 'Perbarui profil akun global, kata sandi opsional, dan peran Central.'
                                : 'Buat akun global baru beserta peran aksesnya pada sistem Central.'}
                        </p>
                    </div>
                </header>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    <section className="grid gap-4 rounded-lg border p-5 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="central-user-name">
                                Nama Lengkap
                            </Label>
                            <Input
                                id="central-user-name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Nama lengkap pengguna"
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="central-user-email">
                                Alamat Email
                            </Label>
                            <Input
                                id="central-user-email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                placeholder="admin@presentify.test"
                                required
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="central-user-password">
                                {isEditing
                                    ? 'Kata Sandi Baru (Opsional)'
                                    : 'Kata Sandi'}
                            </Label>
                            <Input
                                id="central-user-password"
                                type="password"
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                placeholder={
                                    isEditing
                                        ? 'Biarkan kosong jika tidak diubah'
                                        : 'Minimal 8 karakter'
                                }
                                required={!isEditing}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="central-user-password-confirmation">
                                Konfirmasi Kata Sandi
                            </Label>
                            <Input
                                id="central-user-password-confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) =>
                                    setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                placeholder="Ulangi kata sandi"
                                required={!isEditing || data.password !== ''}
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>
                    </section>

                    <section className="flex flex-col gap-3 rounded-lg border p-5">
                        <div className="border-b pb-3">
                            <h2 className="text-base font-semibold">
                                Peran Central
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Pilih satu atau lebih peran yang diberikan pada
                                konteks Central.
                            </p>
                        </div>

                        {roleOptions.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">
                                Belum ada peran Central yang tersedia.
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
                                            htmlFor={`central-role-${role.id}`}
                                            className="flex cursor-pointer items-center gap-3 rounded-md border px-3.5 py-2.5 transition-colors hover:bg-muted/30"
                                        >
                                            <Checkbox
                                                id={`central-role-${role.id}`}
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
                            <Link href={centralUsersIndexRoute.url()}>
                                Batal
                            </Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {isEditing ? 'Simpan Perubahan' : 'Tambah Pengguna'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
