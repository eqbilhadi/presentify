import { Head, Link, router } from '@inertiajs/react';
import { Lock, Pencil, Plus, Search, Shield, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import { usePermission } from '@/hooks/use-permission';
import {
    create as createRoleRoute,
    destroy as destroyRoleRoute,
    edit as editRoleRoute,
} from '@/routes/rbac/roles';
import type { RoleSummary } from '@/types';

type Props = {
    roles: RoleSummary[];
};

export default function RoleIndex({ roles }: Props) {
    const { can } = usePermission();
    const [search, setSearch] = useState('');
    const [roleToDelete, setRoleToDelete] = useState<RoleSummary | null>(null);
    const [deleting, setDeleting] = useState(false);

    const filteredRoles = useMemo(() => {
        const query = search.trim().toLowerCase();
        if (query === '') {
            return roles;
        }

        return roles.filter((role) => role.name.toLowerCase().includes(query));
    }, [roles, search]);

    function confirmDelete() {
        if (!roleToDelete || deleting) {
            return;
        }

        setDeleting(true);
        router.delete(destroyRoleRoute.url(roleToDelete.id), {
            preserveScroll: true,
            onSuccess: () => setRoleToDelete(null),
            onFinish: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title="Peran & Matriks Hak Akses" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Peran &amp; Matriks Hak Akses
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola daftar peran dan atur kombinasi akses menu,
                            aksi, serta izin global pada konteks aktif.
                        </p>
                    </div>

                    {can('roles.create') && (
                        <Button
                            size="sm"
                            className="min-h-10 sm:min-h-9"
                            asChild
                        >
                            <Link href={createRoleRoute.url()}>
                                <Plus className="size-4" aria-hidden="true" />
                                Tambah Peran
                            </Link>
                        </Button>
                    )}
                </header>

                <div className="relative max-w-md">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <label htmlFor="role-search" className="sr-only">
                        Cari peran
                    </label>
                    <Input
                        id="role-search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari nama peran..."
                        className="pl-9"
                    />
                </div>

                {filteredRoles.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-12 text-center">
                        <Shield
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-sm font-medium">
                            {search.trim() !== ''
                                ? 'Tidak ada peran yang cocok dengan pencarian.'
                                : 'Belum ada peran terdaftar pada konteks ini.'}
                        </p>
                        {search.trim() !== '' ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setSearch('')}
                            >
                                Reset Pencarian
                            </Button>
                        ) : (
                            can('roles.create') && (
                                <Button size="sm" asChild>
                                    <Link href={createRoleRoute.url()}>
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Tambah Peran
                                    </Link>
                                </Button>
                            )
                        )}
                    </div>
                ) : (
                    <>
                        <div className="hidden overflow-hidden rounded-lg border md:block">
                            <table className="w-full border-collapse text-left text-sm">
                                <thead className="border-b bg-muted/40 text-xs font-medium text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Nama Peran
                                        </th>
                                        <th className="px-4 py-3">Guard</th>
                                        <th className="px-4 py-3 text-right">
                                            Pengguna
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Menu
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Izin
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Kelola
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {filteredRoles.map((role) => (
                                        <tr
                                            key={role.id}
                                            className="transition-colors hover:bg-muted/25"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono text-sm font-medium">
                                                        {role.name}
                                                    </span>
                                                    {role.is_protected && (
                                                        <Badge variant="secondary">
                                                            <Lock
                                                                className="mr-1 size-3"
                                                                aria-hidden="true"
                                                            />
                                                            Terproteksi
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                {role.guard_name}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {role.users_count}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {role.menus_count}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {role.permissions_count}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    {can('roles.update') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            asChild
                                                            aria-label={`Ubah peran ${role.name}`}
                                                        >
                                                            <Link
                                                                href={editRoleRoute.url(
                                                                    role.id,
                                                                )}
                                                            >
                                                                <Pencil
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {can('roles.delete') &&
                                                        !role.is_protected && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-destructive hover:text-destructive"
                                                                onClick={() =>
                                                                    setRoleToDelete(
                                                                        role,
                                                                    )
                                                                }
                                                                aria-label={`Hapus peran ${role.name}`}
                                                            >
                                                                <Trash2
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <ul className="grid gap-3 md:hidden" role="list">
                            {filteredRoles.map((role) => (
                                <li
                                    key={role.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-mono text-sm font-medium">
                                                    {role.name}
                                                </span>
                                                {role.is_protected && (
                                                    <Badge variant="secondary">
                                                        Terproteksi
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                {role.users_count} pengguna
                                                &middot; {role.menus_count} menu
                                                &middot;{' '}
                                                {role.permissions_count} izin
                                            </p>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-1">
                                            {can('roles.update') && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={editRoleRoute.url(
                                                            role.id,
                                                        )}
                                                    >
                                                        Ubah
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('roles.delete') &&
                                                !role.is_protected && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive"
                                                        onClick={() =>
                                                            setRoleToDelete(
                                                                role,
                                                            )
                                                        }
                                                        aria-label={`Hapus peran ${role.name}`}
                                                    >
                                                        <Trash2
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    </Button>
                                                )}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>

            <Dialog
                open={roleToDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setRoleToDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Peran</DialogTitle>
                        <DialogDescription>
                            Peran{' '}
                            <span className="font-mono font-medium text-foreground">
                                {roleToDelete?.name}
                            </span>{' '}
                            akan dihapus beserta seluruh penetapan menu dan
                            izinnya.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRoleToDelete(null)}
                            disabled={deleting}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmDelete}
                            disabled={deleting}
                        >
                            {deleting && <Spinner />}
                            Hapus Peran
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
