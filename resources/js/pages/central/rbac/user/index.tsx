import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, ShieldCheck, Trash2, Users } from 'lucide-react';
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
    create as createCentralUserRoute,
    destroy as destroyCentralUserRoute,
    edit as editCentralUserRoute,
} from '@/routes/central/rbac/users';
import type { CentralUserListItem, RbacRoleOption } from '@/types';

type Props = {
    users: CentralUserListItem[];
    roleOptions: RbacRoleOption[];
};

export default function CentralUserIndex({ users, roleOptions }: Props) {
    const { auth } = usePage().props;
    const { can } = usePermission();
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState<string>('all');
    const [userToDelete, setUserToDelete] =
        useState<CentralUserListItem | null>(null);
    const [deleting, setDeleting] = useState(false);

    const currentUserId = auth?.user?.id;

    const filteredUsers = useMemo(() => {
        const query = search.trim().toLowerCase();

        return users.filter((user) => {
            const matchesSearch =
                query === '' ||
                user.name.toLowerCase().includes(query) ||
                user.email.toLowerCase().includes(query);

            const matchesRole =
                roleFilter === 'all' ||
                (roleFilter === 'none'
                    ? user.roles.length === 0
                    : user.roles.some((role) => role.id === roleFilter));

            return matchesSearch && matchesRole;
        });
    }, [users, search, roleFilter]);

    const hasActiveFilter = search.trim() !== '' || roleFilter !== 'all';

    function confirmDelete() {
        if (!userToDelete || deleting) {
            return;
        }

        setDeleting(true);
        router.delete(destroyCentralUserRoute.url(userToDelete.id), {
            preserveScroll: true,
            onSuccess: () => setUserToDelete(null),
            onFinish: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title="Manajemen Pengguna Central" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Manajemen Pengguna Central
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola akun global dan penetapan peran admin pusat
                            pada sistem Central.
                        </p>
                    </div>

                    {can('users.create') && (
                        <Button
                            size="sm"
                            className="min-h-10 sm:min-h-9"
                            asChild
                        >
                            <Link href={createCentralUserRoute.url()}>
                                <Plus className="size-4" aria-hidden="true" />
                                Tambah Pengguna
                            </Link>
                        </Button>
                    )}
                </header>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1 sm:max-w-md">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <label
                            htmlFor="central-user-search"
                            className="sr-only"
                        >
                            Cari pengguna
                        </label>
                        <Input
                            id="central-user-search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari nama atau email pengguna..."
                            className="pl-9"
                        />
                    </div>

                    <div className="w-full sm:w-56">
                        <Select
                            value={roleFilter}
                            onValueChange={setRoleFilter}
                        >
                            <SelectTrigger aria-label="Filter peran">
                                <SelectValue placeholder="Semua peran" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua Peran</SelectItem>
                                <SelectItem value="none">
                                    Tanpa Peran Central
                                </SelectItem>
                                {roleOptions.map((role) => (
                                    <SelectItem key={role.id} value={role.id}>
                                        {role.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {filteredUsers.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-12 text-center">
                        <Users
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-sm font-medium">
                            {hasActiveFilter
                                ? 'Tidak ada pengguna yang cocok dengan filter saat ini.'
                                : 'Belum ada pengguna terdaftar pada sistem Central.'}
                        </p>
                        {hasActiveFilter ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    setRoleFilter('all');
                                }}
                            >
                                Reset Filter
                            </Button>
                        ) : (
                            can('users.create') && (
                                <Button size="sm" asChild>
                                    <Link href={createCentralUserRoute.url()}>
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Tambah Pengguna
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
                                        <th className="px-4 py-3">Pengguna</th>
                                        <th className="px-4 py-3">
                                            Peran Central
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Instansi Aktif
                                        </th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">
                                            Kelola
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {filteredUsers.map((user) => {
                                        const isSelf =
                                            currentUserId === user.id;

                                        return (
                                            <tr
                                                key={user.id}
                                                className="transition-colors hover:bg-muted/25"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-col">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium text-foreground">
                                                                {user.name}
                                                            </span>
                                                            {isSelf && (
                                                                <Badge variant="outline">
                                                                    Anda
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <span className="text-xs text-muted-foreground">
                                                            {user.email}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {user.roles.length === 0 ? (
                                                        <span className="text-xs text-muted-foreground">
                                                            —
                                                        </span>
                                                    ) : (
                                                        <div className="flex flex-wrap gap-1.5">
                                                            {user.roles.map(
                                                                (role) => (
                                                                    <Badge
                                                                        key={
                                                                            role.id
                                                                        }
                                                                        variant="secondary"
                                                                        className="font-mono text-xs"
                                                                    >
                                                                        {
                                                                            role.name
                                                                        }
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums">
                                                    {user.tenants_count}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {user.email_verified_at ? (
                                                        <Badge variant="outline">
                                                            <ShieldCheck
                                                                className="mr-1 size-3"
                                                                aria-hidden="true"
                                                            />
                                                            Terverifikasi
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="secondary">
                                                            Belum Verifikasi
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        {can(
                                                            'users.update',
                                                        ) && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                asChild
                                                                aria-label={`Ubah pengguna ${user.name}`}
                                                            >
                                                                <Link
                                                                    href={editCentralUserRoute.url(
                                                                        user.id,
                                                                    )}
                                                                >
                                                                    <Pencil
                                                                        className="size-4"
                                                                        aria-hidden="true"
                                                                    />
                                                                </Link>
                                                            </Button>
                                                        )}
                                                        {can('users.delete') &&
                                                            !isSelf && (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="text-destructive hover:text-destructive"
                                                                    onClick={() =>
                                                                        setUserToDelete(
                                                                            user,
                                                                        )
                                                                    }
                                                                    aria-label={`Hapus pengguna ${user.name}`}
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
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <ul className="grid gap-3 md:hidden" role="list">
                            {filteredUsers.map((user) => {
                                const isSelf = currentUserId === user.id;

                                return (
                                    <li
                                        key={user.id}
                                        className="rounded-lg border p-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="space-y-1.5">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="text-sm font-medium">
                                                        {user.name}
                                                    </span>
                                                    {isSelf && (
                                                        <Badge variant="outline">
                                                            Anda
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {user.email}
                                                </p>
                                                <div className="flex flex-wrap gap-1">
                                                    {user.roles.map((role) => (
                                                        <Badge
                                                            key={role.id}
                                                            variant="secondary"
                                                            className="font-mono text-xs"
                                                        >
                                                            {role.name}
                                                        </Badge>
                                                    ))}
                                                </div>
                                                <p className="text-xs text-muted-foreground tabular-nums">
                                                    {user.tenants_count}{' '}
                                                    instansi aktif
                                                </p>
                                            </div>

                                            <div className="flex shrink-0 items-center gap-1">
                                                {can('users.update') && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={editCentralUserRoute.url(
                                                                user.id,
                                                            )}
                                                        >
                                                            Ubah
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('users.delete') &&
                                                    !isSelf && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-destructive"
                                                            onClick={() =>
                                                                setUserToDelete(
                                                                    user,
                                                                )
                                                            }
                                                            aria-label={`Hapus pengguna ${user.name}`}
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
                                );
                            })}
                        </ul>
                    </>
                )}
            </div>

            <Dialog
                open={userToDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setUserToDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Pengguna Central</DialogTitle>
                        <DialogDescription>
                            Akun{' '}
                            <span className="font-medium text-foreground">
                                {userToDelete?.name}
                            </span>{' '}
                            ({userToDelete?.email}) akan dihapus permanen
                            beserta penetapan perannya di Central.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setUserToDelete(null)}
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
                            Hapus Pengguna
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
