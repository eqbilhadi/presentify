import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Power, Search, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    create as createTenantUserRoute,
    edit as editTenantUserRoute,
    toggleStatus as toggleTenantUserStatusRoute,
} from '@/routes/tenant/rbac/users';
import type { RbacRoleOption, TenantUserListItem } from '@/types';

type Props = {
    users: TenantUserListItem[];
    roleOptions: RbacRoleOption[];
};

export default function TenantUserIndex({ users, roleOptions }: Props) {
    const { auth } = usePage().props;
    const { can } = usePermission();
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState<string>('all');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [togglingUserId, setTogglingUserId] = useState<number | null>(null);

    const currentUserId = auth?.user?.id;

    const filteredUsers = useMemo(() => {
        const query = search.trim().toLowerCase();

        return users.filter((user) => {
            const matchesSearch =
                query === '' ||
                user.name.toLowerCase().includes(query) ||
                user.email.toLowerCase().includes(query) ||
                (user.nip ?? '').toLowerCase().includes(query) ||
                (user.position ?? '').toLowerCase().includes(query);

            const matchesRole =
                roleFilter === 'all' ||
                (roleFilter === 'none'
                    ? user.roles.length === 0
                    : user.roles.some((role) => role.id === roleFilter));

            const matchesStatus =
                statusFilter === 'all' ||
                (statusFilter === 'active' ? user.is_active : !user.is_active);

            return matchesSearch && matchesRole && matchesStatus;
        });
    }, [users, search, roleFilter, statusFilter]);

    const hasActiveFilter =
        search.trim() !== '' || roleFilter !== 'all' || statusFilter !== 'all';

    function handleToggleStatus(user: TenantUserListItem) {
        if (togglingUserId !== null) {
            return;
        }

        setTogglingUserId(user.id);
        router.patch(
            toggleTenantUserStatusRoute.url(user.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setTogglingUserId(null),
            },
        );
    }

    return (
        <>
            <Head title="Manajemen Pengguna Instansi" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Manajemen Pengguna Instansi
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola keanggotaan akun, peran akses lokal, dan
                            status aktif pengguna pada instansi ini.
                        </p>
                    </div>

                    {can('users.create') && (
                        <Button
                            size="sm"
                            className="min-h-10 sm:min-h-9"
                            asChild
                        >
                            <Link href={createTenantUserRoute.url()}>
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
                        <label htmlFor="tenant-user-search" className="sr-only">
                            Cari pengguna instansi
                        </label>
                        <Input
                            id="tenant-user-search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari nama, email, NIP, atau jabatan..."
                            className="pl-9"
                        />
                    </div>

                    <div className="w-full sm:w-48">
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
                                    Tanpa Peran
                                </SelectItem>
                                {roleOptions.map((role) => (
                                    <SelectItem key={role.id} value={role.id}>
                                        {role.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="w-full sm:w-40">
                        <Select
                            value={statusFilter}
                            onValueChange={setStatusFilter}
                        >
                            <SelectTrigger aria-label="Filter status">
                                <SelectValue placeholder="Semua status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua Status
                                </SelectItem>
                                <SelectItem value="active">Aktif</SelectItem>
                                <SelectItem value="inactive">
                                    Nonaktif
                                </SelectItem>
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
                                : 'Belum ada pengguna terdaftar pada instansi ini.'}
                        </p>
                        {hasActiveFilter ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    setRoleFilter('all');
                                    setStatusFilter('all');
                                }}
                            >
                                Reset Filter
                            </Button>
                        ) : (
                            can('users.create') && (
                                <Button size="sm" asChild>
                                    <Link href={createTenantUserRoute.url()}>
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
                                            NIP &amp; Jabatan
                                        </th>
                                        <th className="px-4 py-3">Peran</th>
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
                                        const isToggling =
                                            togglingUserId === user.id;

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
                                                    <div className="flex flex-col">
                                                        <span className="font-mono text-xs tabular-nums">
                                                            {user.nip ?? '—'}
                                                        </span>
                                                        {user.position && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {user.position}
                                                            </span>
                                                        )}
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
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant={
                                                            user.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {user.is_active
                                                            ? 'Aktif'
                                                            : 'Nonaktif'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        {can(
                                                            'users.update',
                                                        ) && (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    asChild
                                                                    aria-label={`Ubah akses ${user.name}`}
                                                                >
                                                                    <Link
                                                                        href={editTenantUserRoute.url(
                                                                            user.id,
                                                                        )}
                                                                    >
                                                                        <Pencil
                                                                            className="size-4"
                                                                            aria-hidden="true"
                                                                        />
                                                                    </Link>
                                                                </Button>
                                                                {!isSelf && (
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        disabled={
                                                                            togglingUserId !==
                                                                            null
                                                                        }
                                                                        onClick={() =>
                                                                            handleToggleStatus(
                                                                                user,
                                                                            )
                                                                        }
                                                                        aria-label={
                                                                            user.is_active
                                                                                ? `Nonaktifkan ${user.name}`
                                                                                : `Aktifkan ${user.name}`
                                                                        }
                                                                    >
                                                                        {isToggling ? (
                                                                            <Spinner />
                                                                        ) : (
                                                                            <Power
                                                                                className="size-4"
                                                                                aria-hidden="true"
                                                                            />
                                                                        )}
                                                                    </Button>
                                                                )}
                                                            </>
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
                                const isToggling = togglingUserId === user.id;

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
                                                    <Badge
                                                        variant={
                                                            user.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {user.is_active
                                                            ? 'Aktif'
                                                            : 'Nonaktif'}
                                                    </Badge>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {user.email}
                                                </p>
                                                {(user.nip ||
                                                    user.position) && (
                                                    <p className="text-xs text-muted-foreground tabular-nums">
                                                        {[
                                                            user.nip,
                                                            user.position,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </p>
                                                )}
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
                                            </div>

                                            {can('users.update') && (
                                                <div className="flex shrink-0 items-center gap-1">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={editTenantUserRoute.url(
                                                                user.id,
                                                            )}
                                                        >
                                                            Ubah
                                                        </Link>
                                                    </Button>
                                                    {!isSelf && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            disabled={
                                                                togglingUserId !==
                                                                null
                                                            }
                                                            onClick={() =>
                                                                handleToggleStatus(
                                                                    user,
                                                                )
                                                            }
                                                            aria-label={
                                                                user.is_active
                                                                    ? `Nonaktifkan ${user.name}`
                                                                    : `Aktifkan ${user.name}`
                                                            }
                                                        >
                                                            {isToggling ? (
                                                                <Spinner />
                                                            ) : (
                                                                <Power
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                            )}
                                                        </Button>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </>
                )}
            </div>
        </>
    );
}
