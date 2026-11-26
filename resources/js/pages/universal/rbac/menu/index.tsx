import { Head, Link, router } from '@inertiajs/react';
import { ArrowUpDown, Pencil, Plus, Power, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
import { resolveMenuIcon } from '@/lib/rbac-icons';
import {
    create as createMenuRoute,
    destroy as destroyMenuRoute,
    edit as editMenuRoute,
    index as menusIndexRoute,
    sort as sortMenusRoute,
    toggleStatus as toggleMenuStatusRoute,
} from '@/routes/rbac/menus';
import type { MenuListItem } from '@/types';

type Props = {
    menus: MenuListItem[];
    filters: {
        search: string;
        status: string;
    };
};

export default function MenuIndex({ menus, filters }: Props) {
    const { can } = usePermission();
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');
    const [togglingId, setTogglingId] = useState<string | null>(null);
    const [menuToDelete, setMenuToDelete] = useState<MenuListItem | null>(null);
    const [deleting, setDeleting] = useState(false);

    function applyFilters(nextSearch: string, nextStatus: string) {
        router.get(
            menusIndexRoute.url({
                query: {
                    ...(nextSearch.trim() !== ''
                        ? { search: nextSearch.trim() }
                        : {}),
                    ...(nextStatus !== 'all' ? { status: nextStatus } : {}),
                },
            }),
            {},
            { preserveState: true, replace: true },
        );
    }

    function handleSearchSubmit(event: React.FormEvent) {
        event.preventDefault();
        applyFilters(search, status);
    }

    function handleStatusChange(value: string) {
        setStatus(value);
        applyFilters(search, value);
    }

    function handleToggleStatus(menu: MenuListItem) {
        if (!can('menus.update') || togglingId !== null) {
            return;
        }

        setTogglingId(menu.id);
        router.patch(
            toggleMenuStatusRoute.url(menu.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setTogglingId(null),
            },
        );
    }

    function handleConfirmDelete() {
        if (!menuToDelete || deleting) {
            return;
        }

        setDeleting(true);
        router.delete(destroyMenuRoute.url(menuToDelete.id), {
            preserveScroll: true,
            onSuccess: () => setMenuToDelete(null),
            onFinish: () => setDeleting(false),
        });
    }

    const hasActiveFilter = search.trim() !== '' || status !== 'all';

    return (
        <>
            <Head title="Manajemen Menu" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Manajemen Menu
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola struktur navigasi sidebar, prefix izin, dan
                            visibilitas menu pada konteks aktif.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {can('menus.update') && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="min-h-10 sm:min-h-9"
                                asChild
                            >
                                <Link href={sortMenusRoute.url()}>
                                    <ArrowUpDown
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Urutkan Menu
                                </Link>
                            </Button>
                        )}
                        {can('menus.create') && (
                            <Button
                                size="sm"
                                className="min-h-10 sm:min-h-9"
                                asChild
                            >
                                <Link href={createMenuRoute.url()}>
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Tambah Menu
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>

                <form
                    onSubmit={handleSearchSubmit}
                    className="flex flex-col gap-3 sm:flex-row sm:items-center"
                >
                    <div className="relative flex-1">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <label htmlFor="menu-search" className="sr-only">
                            Cari menu
                        </label>
                        <Input
                            id="menu-search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari nama menu, URL, route, atau prefix izin..."
                            className="pl-9"
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <Select
                            value={status}
                            onValueChange={handleStatusChange}
                        >
                            <SelectTrigger
                                className="w-40"
                                aria-label="Filter status menu"
                            >
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

                        <Button type="submit" variant="secondary" size="sm">
                            Cari
                        </Button>

                        {hasActiveFilter && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    setStatus('all');
                                    applyFilters('', 'all');
                                }}
                            >
                                Reset
                            </Button>
                        )}
                    </div>
                </form>

                {menus.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-12 text-center">
                        <p className="text-sm font-medium">
                            {hasActiveFilter
                                ? 'Tidak ada menu yang cocok dengan filter.'
                                : 'Belum ada menu terdaftar pada konteks ini.'}
                        </p>
                        <p className="max-w-md text-xs text-muted-foreground">
                            {hasActiveFilter
                                ? 'Ubah kata kunci pencarian atau setel ulang filter status untuk melihat semua menu.'
                                : 'Tambahkan menu navigasi pertama untuk mulai menyusun sidebar dan aksi izin.'}
                        </p>
                        {hasActiveFilter ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    setStatus('all');
                                    applyFilters('', 'all');
                                }}
                            >
                                Setel Ulang Filter
                            </Button>
                        ) : (
                            can('menus.create') && (
                                <Button size="sm" asChild>
                                    <Link href={createMenuRoute.url()}>
                                        <Plus
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Tambah Menu
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
                                        <th className="px-4 py-3">Menu</th>
                                        <th className="px-4 py-3">Tujuan</th>
                                        <th className="px-4 py-3">
                                            Prefix Izin
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Urutan
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Aksi
                                        </th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">
                                            Kelola
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {menus.map((menu) => {
                                        const Icon = resolveMenuIcon(menu.icon);
                                        const isToggling =
                                            togglingId === menu.id;

                                        return (
                                            <tr
                                                key={menu.id}
                                                className="transition-colors hover:bg-muted/25"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2.5">
                                                        {!menu.is_divider && (
                                                            <span className="flex size-7 shrink-0 items-center justify-center rounded-md border bg-muted/40 text-foreground">
                                                                <Icon
                                                                    className="size-3.5"
                                                                    aria-hidden="true"
                                                                />
                                                            </span>
                                                        )}
                                                        <div className="min-w-0">
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-medium">
                                                                    {
                                                                        menu.label_name
                                                                    }
                                                                </span>
                                                                {menu.is_divider && (
                                                                    <Badge variant="outline">
                                                                        Divider
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            {menu.parent_label && (
                                                                <p className="text-xs text-muted-foreground">
                                                                    Induk:{' '}
                                                                    {
                                                                        menu.parent_label
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                    {menu.url ??
                                                        menu.route_name ??
                                                        '-'}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">
                                                    {menu.permission_prefix ??
                                                        '-'}
                                                </td>
                                                <td className="px-4 py-3 text-right font-mono text-xs tabular-nums">
                                                    {menu.sort_num}
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums">
                                                    {menu.actions_count}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant={
                                                            menu.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {menu.is_active
                                                            ? 'Aktif'
                                                            : 'Nonaktif'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        {can(
                                                            'menus.update',
                                                        ) && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    handleToggleStatus(
                                                                        menu,
                                                                    )
                                                                }
                                                                disabled={
                                                                    isToggling
                                                                }
                                                                aria-label={
                                                                    menu.is_active
                                                                        ? `Nonaktifkan ${menu.label_name}`
                                                                        : `Aktifkan ${menu.label_name}`
                                                                }
                                                            >
                                                                {isToggling ? (
                                                                    <Spinner className="size-4" />
                                                                ) : (
                                                                    <Power
                                                                        className="size-4"
                                                                        aria-hidden="true"
                                                                    />
                                                                )}
                                                            </Button>
                                                        )}

                                                        {can(
                                                            'menus.update',
                                                        ) && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                asChild
                                                                aria-label={`Ubah ${menu.label_name}`}
                                                            >
                                                                <Link
                                                                    href={editMenuRoute.url(
                                                                        menu.id,
                                                                    )}
                                                                >
                                                                    <Pencil
                                                                        className="size-4"
                                                                        aria-hidden="true"
                                                                    />
                                                                </Link>
                                                            </Button>
                                                        )}

                                                        {can(
                                                            'menus.delete',
                                                        ) && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-destructive hover:text-destructive"
                                                                onClick={() =>
                                                                    setMenuToDelete(
                                                                        menu,
                                                                    )
                                                                }
                                                                aria-label={`Hapus ${menu.label_name}`}
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
                            {menus.map((menu) => (
                                <li
                                    key={menu.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {menu.label_name}
                                                </span>
                                                <Badge
                                                    variant={
                                                        menu.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {menu.is_active
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </Badge>
                                            </div>
                                            {menu.parent_label && (
                                                <p className="text-xs text-muted-foreground">
                                                    Induk: {menu.parent_label}
                                                </p>
                                            )}
                                            <p className="font-mono text-xs text-muted-foreground">
                                                {menu.url ??
                                                    menu.route_name ??
                                                    '-'}{' '}
                                                &middot; Urutan{' '}
                                                <span className="tabular-nums">
                                                    {menu.sort_num}
                                                </span>
                                            </p>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-1">
                                            {can('menus.update') && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={editMenuRoute.url(
                                                            menu.id,
                                                        )}
                                                    >
                                                        Ubah
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('menus.delete') && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive"
                                                    onClick={() =>
                                                        setMenuToDelete(menu)
                                                    }
                                                    aria-label={`Hapus ${menu.label_name}`}
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
                open={menuToDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setMenuToDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Menu</DialogTitle>
                        <DialogDescription>
                            Menghapus menu{' '}
                            <span className="font-medium text-foreground">
                                {menuToDelete?.label_name}
                            </span>{' '}
                            akan ikut menghapus seluruh aksi dan izin terkait
                            pada konteks ini.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setMenuToDelete(null)}
                            disabled={deleting}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleConfirmDelete}
                            disabled={deleting}
                        >
                            {deleting && <Spinner />}
                            Hapus Menu
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
