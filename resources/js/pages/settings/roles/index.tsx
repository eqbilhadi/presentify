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
import { roles as rolesIndexRoute } from '@/routes/settings';
import {
    create as createRoleRoute,
    destroy as destroyRoleRoute,
    edit as editRoleRoute,
} from '@/routes/settings/roles';

export type RoleSummary = {
    id: number;
    name: string;
    label: string;
    description: string | null;
    is_system: boolean;
    members_count: number;
    menu_permissions_count: number;
    global_permissions_count: number;
};

type RoleFilter = 'all' | 'system' | 'custom';

type Props = {
    roles: RoleSummary[];
};

export default function RolesIndex({ roles }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [filterType, setFilterType] = useState<RoleFilter>('all');
    const [roleToDelete, setRoleToDelete] = useState<RoleSummary | null>(null);
    const [deleting, setDeleting] = useState(false);

    const filteredRoles = useMemo(() => {
        const normalizedQuery = searchQuery.trim().toLowerCase();

        return roles.filter((role) => {
            if (filterType === 'system' && !role.is_system) {
                return false;
            }
            if (filterType === 'custom' && role.is_system) {
                return false;
            }
            if (normalizedQuery === '') {
                return true;
            }

            return (
                role.label.toLowerCase().includes(normalizedQuery) ||
                role.name.toLowerCase().includes(normalizedQuery) ||
                (role.description ?? '').toLowerCase().includes(normalizedQuery)
            );
        });
    }, [roles, searchQuery, filterType]);

    function handleConfirmDelete() {
        if (!roleToDelete) {
            return;
        }

        setDeleting(true);
        router.delete(destroyRoleRoute.url(roleToDelete.id), {
            preserveScroll: true,
            onSuccess: () => {
                setRoleToDelete(null);
            },
            onFinish: () => {
                setDeleting(false);
            },
        });
    }

    function resetFilters() {
        setSearchQuery('');
        setFilterType('all');
    }

    return (
        <>
            <Head title="Peran dan Hak Akses" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Peran dan Hak Akses
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Atur peran bawaan atau buat peran kustom untuk membatasi akses menu (Tier 1) dan izin lintas modul (Tier 2).
                        </p>
                    </div>

                    <Button asChild className="min-h-11 sm:min-h-9 shrink-0">
                        <Link href={createRoleRoute.url()}>
                            <Plus aria-hidden="true" />
                            Buat Peran Baru
                        </Link>
                    </Button>
                </header>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full sm:max-w-xs">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            type="search"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Cari nama atau kode peran..."
                            aria-label="Cari peran"
                            className="pl-9"
                        />
                    </div>

                    <div
                        className="flex flex-wrap items-center gap-1.5"
                        role="group"
                        aria-label="Filter jenis peran"
                    >
                        {(
                            [
                                { key: 'all', label: 'Semua' },
                                { key: 'system', label: 'Bawaan Sistem' },
                                { key: 'custom', label: 'Kustom' },
                            ] as const
                        ).map((tab) => (
                            <Button
                                key={tab.key}
                                type="button"
                                size="sm"
                                variant={filterType === tab.key ? 'default' : 'outline'}
                                onClick={() => setFilterType(tab.key)}
                                aria-pressed={filterType === tab.key}
                                className="min-h-10 sm:min-h-8"
                            >
                                {tab.label}
                            </Button>
                        ))}
                    </div>
                </div>

                {roles.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed px-6 py-12 text-center">
                        <Shield className="size-8 text-muted-foreground" aria-hidden="true" />
                        <div className="space-y-1">
                            <p className="text-sm font-medium">Belum ada peran terdaftar di instansi ini.</p>
                            <p className="text-sm text-muted-foreground">
                                Buat peran baru atau jalankan seeder RBAC untuk memuat 5 peran standar sistem.
                            </p>
                        </div>
                        <Button asChild size="sm" className="mt-1 min-h-10 sm:min-h-8">
                            <Link href={createRoleRoute.url()}>Buat Peran Pertama</Link>
                        </Button>
                    </div>
                ) : filteredRoles.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed px-6 py-12 text-center">
                        <p className="text-sm font-medium">
                            Tidak ada peran yang sesuai dengan pencarian Anda.
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Coba kata kunci lain atau tampilkan semua jenis peran.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={resetFilters}
                            className="min-h-10 sm:min-h-8"
                        >
                            Reset Filter
                        </Button>
                    </div>
                ) : (
                    <>
                        {/* Desktop table */}
                        <div className="hidden overflow-hidden rounded-lg border md:block">
                            <table className="w-full border-collapse text-left text-sm">
                                <thead className="border-b bg-muted/40 text-xs font-medium text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="px-4 py-3">Peran</th>
                                        <th scope="col" className="px-4 py-3">Jenis</th>
                                        <th scope="col" className="px-4 py-3 text-right">Izin Menu</th>
                                        <th scope="col" className="px-4 py-3 text-right">Izin Global</th>
                                        <th scope="col" className="px-4 py-3 text-right">Anggota</th>
                                        <th scope="col" className="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {filteredRoles.map((role) => (
                                        <tr
                                            key={role.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3.5 align-top">
                                                <div className="font-medium text-foreground">
                                                    {role.label}
                                                </div>
                                                <div className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                    {role.name}
                                                </div>
                                                {role.description && (
                                                    <p className="mt-1 max-w-md text-xs text-muted-foreground">
                                                        {role.description}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 align-top">
                                                {role.is_system ? (
                                                    <Badge variant="secondary">
                                                        <Lock aria-hidden="true" />
                                                        Bawaan
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">Kustom</Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3.5 text-right align-top tabular-nums">
                                                {role.menu_permissions_count}
                                            </td>
                                            <td className="px-4 py-3.5 text-right align-top tabular-nums">
                                                {role.global_permissions_count}
                                            </td>
                                            <td className="px-4 py-3.5 text-right align-top tabular-nums">
                                                {role.members_count}
                                            </td>
                                            <td className="px-4 py-3.5 text-right align-top">
                                                <div className="inline-flex items-center justify-end gap-2">
                                                    <Button asChild variant="outline" size="sm">
                                                        <Link href={editRoleRoute.url(role.id)}>
                                                            <Pencil aria-hidden="true" />
                                                            Atur Izin
                                                        </Link>
                                                    </Button>
                                                    {!role.is_system && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                            onClick={() => setRoleToDelete(role)}
                                                            aria-label={`Hapus peran ${role.label}`}
                                                        >
                                                            <Trash2 aria-hidden="true" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Mobile cards */}
                        <ul className="grid gap-3 md:hidden" role="list">
                            {filteredRoles.map((role) => (
                                <li
                                    key={role.id}
                                    className="flex flex-col gap-3 rounded-lg border bg-card p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="font-medium text-foreground">
                                                {role.label}
                                            </div>
                                            <div className="mt-0.5 truncate font-mono text-xs text-muted-foreground">
                                                {role.name}
                                            </div>
                                        </div>
                                        {role.is_system ? (
                                            <Badge variant="secondary">
                                                <Lock aria-hidden="true" />
                                                Bawaan
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">Kustom</Badge>
                                        )}
                                    </div>

                                    {role.description && (
                                        <p className="text-xs text-muted-foreground">
                                            {role.description}
                                        </p>
                                    )}

                                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        <span>Izin menu: {role.menu_permissions_count}</span>
                                        <span>Izin global: {role.global_permissions_count}</span>
                                        <span>Anggota: {role.members_count}</span>
                                    </div>

                                    <div className="flex items-center gap-2 pt-1">
                                        <Button asChild variant="outline" className="min-h-11 flex-1">
                                            <Link href={editRoleRoute.url(role.id)}>
                                                <Pencil aria-hidden="true" />
                                                Atur Izin
                                            </Link>
                                        </Button>
                                        {!role.is_system && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="min-h-11 px-3 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                onClick={() => setRoleToDelete(role)}
                                                aria-label={`Hapus peran ${role.label}`}
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        )}
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
                        <DialogTitle>Hapus Peran Kustom</DialogTitle>
                        <DialogDescription>
                            Apakah Anda yakin ingin menghapus peran{' '}
                            <span className="font-medium text-foreground">
                                {roleToDelete?.label}
                            </span>
                            ? Anggota yang memegang peran ini akan kehilangan hak akses terkait.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRoleToDelete(null)}
                            disabled={deleting}
                            className="min-h-11 sm:min-h-9"
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleConfirmDelete}
                            disabled={deleting}
                            className="min-h-11 sm:min-h-9"
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

RolesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Peran dan Hak Akses',
            href: rolesIndexRoute(),
        },
    ],
};
