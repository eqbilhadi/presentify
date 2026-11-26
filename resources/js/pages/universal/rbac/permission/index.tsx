import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    KeyRound,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import {
    destroy as destroyMenuActionRoute,
    store as storeMenuActionRoute,
    update as updateMenuActionRoute,
} from '@/routes/rbac/menu-actions';
import {
    destroy as destroyPermissionRoute,
    index as permissionsIndexRoute,
    store as storePermissionRoute,
    update as updatePermissionRoute,
} from '@/routes/rbac/permissions';
import type {
    MenuActionItem,
    MenuActionsGroup,
    PaginatedPermissions,
    StandalonePermission,
} from '@/types';

type Props = {
    menuGroups: MenuActionsGroup[];
    standalonePermissions: PaginatedPermissions;
    groups: string[];
    filters: {
        search: string;
        group: string;
    };
};

type ActiveTab = 'menu-actions' | 'standalone';

const ALL_GROUPS = '__all__';

export default function PermissionIndex({
    menuGroups,
    standalonePermissions,
    groups,
    filters,
}: Props) {
    const { can } = usePermission();
    const [activeTab, setActiveTab] = useState<ActiveTab>('menu-actions');
    const [search, setSearch] = useState(filters.search);
    const [selectedGroup, setSelectedGroup] = useState(
        filters.group || ALL_GROUPS,
    );

    const [actionDialogMenu, setActionDialogMenu] =
        useState<MenuActionsGroup | null>(null);
    const [editingAction, setEditingAction] = useState<MenuActionItem | null>(
        null,
    );
    const [actionToDelete, setActionToDelete] = useState<MenuActionItem | null>(
        null,
    );
    const [deletingAction, setDeletingAction] = useState(false);

    const [standaloneDialogOpen, setStandaloneDialogOpen] = useState(false);
    const [editingStandalone, setEditingStandalone] =
        useState<StandalonePermission | null>(null);
    const [standaloneToDelete, setStandaloneToDelete] =
        useState<StandalonePermission | null>(null);
    const [deletingStandalone, setDeletingStandalone] = useState(false);

    const actionForm = useForm({
        action: '',
        label: '',
        route_name: '',
        order: 1,
    });

    const standaloneForm = useForm({
        name: '',
        group: '',
    });

    function openCreateAction(menu: MenuActionsGroup) {
        setEditingAction(null);
        setActionDialogMenu(menu);
        actionForm.reset();
        actionForm.clearErrors();
        actionForm.setData({
            action: '',
            label: '',
            route_name: '',
            order: menu.actions.length + 1,
        });
    }

    function openEditAction(menu: MenuActionsGroup, item: MenuActionItem) {
        setEditingAction(item);
        setActionDialogMenu(menu);
        actionForm.clearErrors();
        actionForm.setData({
            action: item.action,
            label: item.label,
            route_name: item.route_name ?? '',
            order: item.order,
        });
    }

    function submitMenuAction(event: React.FormEvent) {
        event.preventDefault();
        if (!actionDialogMenu) {
            return;
        }

        if (editingAction) {
            actionForm.put(updateMenuActionRoute.url(editingAction.id), {
                preserveScroll: true,
                onSuccess: () => {
                    setActionDialogMenu(null);
                    setEditingAction(null);
                },
            });
            return;
        }

        actionForm.post(storeMenuActionRoute.url(actionDialogMenu.id), {
            preserveScroll: true,
            onSuccess: () => {
                setActionDialogMenu(null);
            },
        });
    }

    function confirmDeleteMenuAction() {
        if (!actionToDelete || deletingAction) {
            return;
        }

        setDeletingAction(true);
        router.delete(destroyMenuActionRoute.url(actionToDelete.id), {
            preserveScroll: true,
            onSuccess: () => setActionToDelete(null),
            onFinish: () => setDeletingAction(false),
        });
    }

    function openCreateStandalone() {
        setEditingStandalone(null);
        standaloneForm.reset();
        standaloneForm.clearErrors();
        setStandaloneDialogOpen(true);
    }

    function openEditStandalone(permission: StandalonePermission) {
        setEditingStandalone(permission);
        standaloneForm.clearErrors();
        standaloneForm.setData({
            name: permission.name,
            group: permission.group ?? '',
        });
        setStandaloneDialogOpen(true);
    }

    function submitStandalone(event: React.FormEvent) {
        event.preventDefault();

        if (editingStandalone) {
            standaloneForm.put(
                updatePermissionRoute.url(editingStandalone.id),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setStandaloneDialogOpen(false);
                        setEditingStandalone(null);
                    },
                },
            );
            return;
        }

        standaloneForm.post(storePermissionRoute.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setStandaloneDialogOpen(false);
            },
        });
    }

    function confirmDeleteStandalone() {
        if (!standaloneToDelete || deletingStandalone) {
            return;
        }

        setDeletingStandalone(true);
        router.delete(destroyPermissionRoute.url(standaloneToDelete.id), {
            preserveScroll: true,
            onSuccess: () => setStandaloneToDelete(null),
            onFinish: () => setDeletingStandalone(false),
        });
    }

    function applyStandaloneFilters(nextSearch: string, nextGroup: string) {
        router.get(
            permissionsIndexRoute.url({
                query: {
                    ...(nextSearch.trim() !== ''
                        ? { search: nextSearch.trim() }
                        : {}),
                    ...(nextGroup !== ALL_GROUPS ? { group: nextGroup } : {}),
                },
            }),
            {},
            { preserveState: true, replace: true },
        );
    }

    const previewPermissionName = `${actionDialogMenu?.permission_prefix ?? 'prefix'}.${actionForm.data.action || 'action'}`;

    return (
        <>
            <Head title="Hak Akses (Permission)" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Hak Akses &amp; Aksi Menu
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola aksi granular terikat menu dan izin global
                            standalone dalam satu konteks database.
                        </p>
                    </div>

                    {activeTab === 'standalone' &&
                        can('permissions.create') && (
                            <Button
                                type="button"
                                size="sm"
                                onClick={openCreateStandalone}
                                className="min-h-10 sm:min-h-9"
                            >
                                <Plus className="size-4" aria-hidden="true" />
                                Tambah Hak Akses
                            </Button>
                        )}
                </header>

                <div
                    role="tablist"
                    aria-label="Kategori hak akses"
                    className="inline-flex w-fit rounded-lg border bg-muted/40 p-1"
                >
                    <button
                        type="button"
                        role="tab"
                        aria-selected={activeTab === 'menu-actions'}
                        onClick={() => setActiveTab('menu-actions')}
                        className={cn(
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            activeTab === 'menu-actions'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        Menu Actions ({menuGroups.length})
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={activeTab === 'standalone'}
                        onClick={() => setActiveTab('standalone')}
                        className={cn(
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            activeTab === 'standalone'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        Standalone Permissions ({standalonePermissions.total})
                    </button>
                </div>

                {activeTab === 'menu-actions' ? (
                    menuGroups.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                            Belum ada menu untuk dikelola aksinya.
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {menuGroups.map((menu) => (
                                <Collapsible
                                    key={menu.id}
                                    defaultOpen={menu.actions.length > 0}
                                    className="rounded-lg border bg-card"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                        <CollapsibleTrigger className="flex min-w-0 flex-1 items-center gap-2.5 text-left">
                                            <ChevronDown
                                                className="size-4 shrink-0 text-muted-foreground transition-transform [[data-state=closed]_&]:-rotate-90"
                                                aria-hidden="true"
                                            />
                                            <span className="truncate font-medium">
                                                {menu.label_name}
                                            </span>
                                            {menu.parent_label && (
                                                <span className="text-xs text-muted-foreground">
                                                    ({menu.parent_label})
                                                </span>
                                            )}
                                            {menu.permission_prefix ? (
                                                <Badge
                                                    variant="outline"
                                                    className="font-mono text-xs"
                                                >
                                                    {menu.permission_prefix}
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    Tanpa Prefix
                                                </Badge>
                                            )}
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {menu.actions.length} aksi
                                            </span>
                                        </CollapsibleTrigger>

                                        {can('permissions.create') &&
                                            menu.permission_prefix && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        openCreateAction(menu)
                                                    }
                                                >
                                                    <Plus
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    Tambah Aksi
                                                </Button>
                                            )}
                                    </div>

                                    <CollapsibleContent className="border-t">
                                        {menu.actions.length === 0 ? (
                                            <p className="px-4 py-4 text-xs text-muted-foreground">
                                                {menu.permission_prefix
                                                    ? 'Belum ada aksi terdaftar pada menu ini.'
                                                    : 'Menu ini bertipe grup tanpa permission_prefix.'}
                                            </p>
                                        ) : (
                                            <div className="overflow-x-auto">
                                                <table className="w-full border-collapse text-left text-sm">
                                                    <thead className="border-b bg-muted/30 text-xs font-medium text-muted-foreground">
                                                        <tr>
                                                            <th className="px-4 py-2">
                                                                Label Aksi
                                                            </th>
                                                            <th className="px-4 py-2">
                                                                Slug Aksi
                                                            </th>
                                                            <th className="px-4 py-2">
                                                                Permission
                                                                Tersinkron
                                                            </th>
                                                            <th className="px-4 py-2">
                                                                Route
                                                            </th>
                                                            <th className="px-4 py-2 text-right">
                                                                Urutan
                                                            </th>
                                                            <th className="px-4 py-2 text-right">
                                                                Kelola
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y">
                                                        {menu.actions.map(
                                                            (item) => (
                                                                <tr
                                                                    key={
                                                                        item.id
                                                                    }
                                                                    className="hover:bg-muted/20"
                                                                >
                                                                    <td className="px-4 py-2.5 font-medium">
                                                                        {
                                                                            item.label
                                                                        }
                                                                    </td>
                                                                    <td className="px-4 py-2.5 font-mono text-xs">
                                                                        {
                                                                            item.action
                                                                        }
                                                                    </td>
                                                                    <td className="px-4 py-2.5 font-mono text-xs text-primary">
                                                                        {
                                                                            item.permission_name
                                                                        }
                                                                    </td>
                                                                    <td className="px-4 py-2.5 font-mono text-xs text-muted-foreground">
                                                                        {item.route_name ??
                                                                            '-'}
                                                                    </td>
                                                                    <td className="px-4 py-2.5 text-right font-mono text-xs tabular-nums">
                                                                        {
                                                                            item.order
                                                                        }
                                                                    </td>
                                                                    <td className="px-4 py-2.5">
                                                                        <div className="flex items-center justify-end gap-1">
                                                                            {can(
                                                                                'permissions.update',
                                                                            ) && (
                                                                                <Button
                                                                                    type="button"
                                                                                    variant="ghost"
                                                                                    size="icon"
                                                                                    onClick={() =>
                                                                                        openEditAction(
                                                                                            menu,
                                                                                            item,
                                                                                        )
                                                                                    }
                                                                                    aria-label={`Ubah aksi ${item.permission_name}`}
                                                                                >
                                                                                    <Pencil
                                                                                        className="size-3.5"
                                                                                        aria-hidden="true"
                                                                                    />
                                                                                </Button>
                                                                            )}
                                                                            {can(
                                                                                'permissions.delete',
                                                                            ) && (
                                                                                <Button
                                                                                    type="button"
                                                                                    variant="ghost"
                                                                                    size="icon"
                                                                                    className="text-destructive hover:text-destructive"
                                                                                    onClick={() =>
                                                                                        setActionToDelete(
                                                                                            item,
                                                                                        )
                                                                                    }
                                                                                    aria-label={`Hapus aksi ${item.permission_name}`}
                                                                                >
                                                                                    <Trash2
                                                                                        className="size-3.5"
                                                                                        aria-hidden="true"
                                                                                    />
                                                                                </Button>
                                                                            )}
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </CollapsibleContent>
                                </Collapsible>
                            ))}
                        </div>
                    )
                ) : (
                    <div className="flex flex-col gap-4">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                applyStandaloneFilters(search, selectedGroup);
                            }}
                            className="flex flex-col gap-3 sm:flex-row sm:items-center"
                        >
                            <div className="relative flex-1">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <label
                                    htmlFor="standalone-search"
                                    className="sr-only"
                                >
                                    Cari izin standalone
                                </label>
                                <Input
                                    id="standalone-search"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari nama izin atau grup..."
                                    className="pl-9"
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <Select
                                    value={selectedGroup}
                                    onValueChange={(value) => {
                                        setSelectedGroup(value);
                                        applyStandaloneFilters(search, value);
                                    }}
                                >
                                    <SelectTrigger
                                        className="w-44"
                                        aria-label="Filter grup izin"
                                    >
                                        <SelectValue placeholder="Semua grup" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL_GROUPS}>
                                            Semua Grup
                                        </SelectItem>
                                        {groups.map((grp) => (
                                            <SelectItem key={grp} value={grp}>
                                                {grp}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Button
                                    type="submit"
                                    variant="secondary"
                                    size="sm"
                                >
                                    Cari
                                </Button>
                            </div>
                        </form>

                        {standalonePermissions.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed px-6 py-10 text-center">
                                <KeyRound
                                    className="size-7 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <p className="text-sm font-medium">
                                    Belum ada izin standalone yang cocok.
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Tambahkan izin lintas modul seperti override
                                    kehadiran atau audit sistem.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-lg border">
                                <table className="w-full border-collapse text-left text-sm">
                                    <thead className="border-b bg-muted/40 text-xs font-medium text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Nama Permission
                                            </th>
                                            <th className="px-4 py-3">Grup</th>
                                            <th className="px-4 py-3">Guard</th>
                                            <th className="px-4 py-3 text-right">
                                                Kelola
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {standalonePermissions.data.map(
                                            (perm) => (
                                                <tr
                                                    key={perm.id}
                                                    className="hover:bg-muted/20"
                                                >
                                                    <td className="px-4 py-3 font-mono text-xs font-medium">
                                                        {perm.name}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {perm.group ? (
                                                            <Badge variant="outline">
                                                                {perm.group}
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">
                                                                Umum
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                        {perm.guard_name}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center justify-end gap-1">
                                                            {can(
                                                                'permissions.update',
                                                            ) && (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() =>
                                                                        openEditStandalone(
                                                                            perm,
                                                                        )
                                                                    }
                                                                    aria-label={`Ubah izin ${perm.name}`}
                                                                >
                                                                    <Pencil
                                                                        className="size-4"
                                                                        aria-hidden="true"
                                                                    />
                                                                </Button>
                                                            )}
                                                            {can(
                                                                'permissions.delete',
                                                            ) && (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="text-destructive hover:text-destructive"
                                                                    onClick={() =>
                                                                        setStandaloneToDelete(
                                                                            perm,
                                                                        )
                                                                    }
                                                                    aria-label={`Hapus izin ${perm.name}`}
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
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {standalonePermissions.last_page > 1 && (
                            <nav
                                aria-label="Navigasi halaman permission"
                                className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
                            >
                                <span className="tabular-nums">
                                    Halaman {standalonePermissions.current_page}{' '}
                                    dari {standalonePermissions.last_page} (
                                    {standalonePermissions.total} izin)
                                </span>
                                <div className="flex flex-wrap items-center gap-1">
                                    {standalonePermissions.links.map(
                                        (link, idx) =>
                                            link.url ? (
                                                <Button
                                                    key={idx}
                                                    variant={
                                                        link.active
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={link.url}
                                                        preserveState
                                                    >
                                                        {link.label
                                                            .replace(
                                                                '&laquo;',
                                                                '<<',
                                                            )
                                                            .replace(
                                                                '&raquo;',
                                                                '>>',
                                                            )}
                                                    </Link>
                                                </Button>
                                            ) : null,
                                    )}
                                </div>
                            </nav>
                        )}
                    </div>
                )}
            </div>

            <Dialog
                open={actionDialogMenu !== null}
                onOpenChange={(open) => {
                    if (!open && !actionForm.processing) {
                        setActionDialogMenu(null);
                        setEditingAction(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingAction
                                ? `Ubah Aksi (${actionDialogMenu?.label_name})`
                                : `Tambah Aksi (${actionDialogMenu?.label_name})`}
                        </DialogTitle>
                        <DialogDescription>
                            Aksi menu otomatis disinkronkan ke tabel{' '}
                            <span className="font-mono">rbac_permissions</span>.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={submitMenuAction}
                        className="flex flex-col gap-4"
                    >
                        <div className="rounded-md border bg-muted/30 px-3 py-2 text-xs">
                            <span className="text-muted-foreground">
                                Pratinjau Permission:{' '}
                            </span>
                            <span className="font-mono font-medium text-foreground">
                                {previewPermissionName}
                            </span>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ma-action">Slug Aksi</Label>
                            <Input
                                id="ma-action"
                                value={actionForm.data.action}
                                onChange={(e) =>
                                    actionForm.setData('action', e.target.value)
                                }
                                placeholder="view / create / update / delete / export"
                                className="font-mono text-sm"
                                required
                            />
                            <InputError message={actionForm.errors.action} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="ma-label">Label Tampilan</Label>
                            <Input
                                id="ma-label"
                                value={actionForm.data.label}
                                onChange={(e) =>
                                    actionForm.setData('label', e.target.value)
                                }
                                placeholder="Lihat / Tambah / Ekspor"
                                required
                            />
                            <InputError message={actionForm.errors.label} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="ma-route">
                                    Nama Route (Opsional)
                                </Label>
                                <Input
                                    id="ma-route"
                                    value={actionForm.data.route_name}
                                    onChange={(e) =>
                                        actionForm.setData(
                                            'route_name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="rbac.menus.index"
                                    className="font-mono text-sm"
                                />
                                <InputError
                                    message={actionForm.errors.route_name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="ma-order">Urutan</Label>
                                <Input
                                    id="ma-order"
                                    type="number"
                                    min={0}
                                    value={actionForm.data.order}
                                    onChange={(e) =>
                                        actionForm.setData(
                                            'order',
                                            Number(e.target.value) || 0,
                                        )
                                    }
                                    className="tabular-nums"
                                    required
                                />
                                <InputError message={actionForm.errors.order} />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setActionDialogMenu(null)}
                                disabled={actionForm.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={actionForm.processing}
                            >
                                {actionForm.processing && <Spinner />}
                                Simpan Aksi
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={actionToDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deletingAction) {
                        setActionToDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Aksi Menu</DialogTitle>
                        <DialogDescription>
                            Aksi{' '}
                            <span className="font-mono font-medium text-foreground">
                                {actionToDelete?.permission_name}
                            </span>{' '}
                            dan permission terkait akan dihapus permanen.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setActionToDelete(null)}
                            disabled={deletingAction}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmDeleteMenuAction}
                            disabled={deletingAction}
                        >
                            {deletingAction && <Spinner />}
                            Hapus Aksi
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={standaloneDialogOpen}
                onOpenChange={(open) => {
                    if (!open && !standaloneForm.processing) {
                        setStandaloneDialogOpen(false);
                        setEditingStandalone(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingStandalone
                                ? 'Ubah Standalone Permission'
                                : 'Tambah Standalone Permission'}
                        </DialogTitle>
                        <DialogDescription>
                            Digunakan untuk hak akses fungsional lintas modul
                            yang tidak terikat pada satu menu.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={submitStandalone}
                        className="flex flex-col gap-4"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="sp-name">Nama Permission</Label>
                            <Input
                                id="sp-name"
                                value={standaloneForm.data.name}
                                onChange={(e) =>
                                    standaloneForm.setData(
                                        'name',
                                        e.target.value,
                                    )
                                }
                                placeholder="attendance.bypass_geofence"
                                className="font-mono text-sm"
                                required
                            />
                            <InputError message={standaloneForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="sp-group">Grup (Opsional)</Label>
                            <Input
                                id="sp-group"
                                value={standaloneForm.data.group}
                                onChange={(e) =>
                                    standaloneForm.setData(
                                        'group',
                                        e.target.value,
                                    )
                                }
                                placeholder="Presensi / AI & Audit / Platform"
                            />
                            <InputError message={standaloneForm.errors.group} />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setStandaloneDialogOpen(false)}
                                disabled={standaloneForm.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={standaloneForm.processing}
                            >
                                {standaloneForm.processing && <Spinner />}
                                Simpan Permission
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={standaloneToDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deletingStandalone) {
                        setStandaloneToDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Standalone Permission</DialogTitle>
                        <DialogDescription>
                            Permission{' '}
                            <span className="font-mono font-medium text-foreground">
                                {standaloneToDelete?.name}
                            </span>{' '}
                            akan dicabut dari seluruh peran yang memilikinya.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStandaloneToDelete(null)}
                            disabled={deletingStandalone}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmDeleteStandalone}
                            disabled={deletingStandalone}
                        >
                            {deletingStandalone && <Spinner />}
                            Hapus Permission
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
