import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock } from 'lucide-react';
import type { FormEvent } from 'react';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { roles as rolesIndexRoute } from '@/routes/settings';
import {
    create as createRoleRoute,
    edit as editRoleRoute,
    store as storeRoleRoute,
    update as updateRoleRoute,
} from '@/routes/settings/roles';

type PermissionItem = {
    id: number;
    name: string;
    label: string;
    action: string;
};

type ActionColumn = {
    key: string;
    label: string;
};

type MenuMatrixRow = {
    id: number;
    key: string;
    label: string;
    parent_id: number | null;
    parent_label: string | null;
    is_group: boolean;
    permissions: Record<string, PermissionItem>;
};

type PermissionMatrix = {
    actions: ActionColumn[];
    menu_rows: MenuMatrixRow[];
    global_permissions: PermissionItem[];
};

type EditableRole = {
    id: number;
    name: string;
    label: string;
    description: string | null;
    is_system: boolean;
    permission_ids: number[];
};

type Props = {
    role: EditableRole | null;
    matrix: PermissionMatrix;
};

export default function RoleEditor({ role, matrix }: Props) {
    const isEditing = role !== null;

    const form = useForm<{
        label: string;
        name: string;
        description: string;
        permission_ids: number[];
    }>({
        label: role?.label ?? '',
        name: role?.name ?? '',
        description: role?.description ?? '',
        permission_ids: role?.permission_ids ?? [],
    });

    const selectedIds = new Set(form.data.permission_ids);

    function setPermissionIds(nextIds: Set<number>) {
        form.setData('permission_ids', Array.from(nextIds).sort((a, b) => a - b));
    }

    function toggleMenuPermission(row: MenuMatrixRow, action: string, checked: boolean) {
        const target = row.permissions[action];
        if (!target) {
            return;
        }

        const next = new Set(selectedIds);
        const viewPerm = row.permissions.view;

        if (checked) {
            next.add(target.id);
            // PRD 3.2: enabling any non-view action automatically enables `view` for that menu.
            if (action !== 'view' && viewPerm) {
                next.add(viewPerm.id);
            }
        } else {
            next.delete(target.id);
            // Disabling `view` clears dependent menu actions so permissions stay consistent.
            if (action === 'view') {
                Object.values(row.permissions).forEach((perm) => {
                    next.delete(perm.id);
                });
            }
        }

        setPermissionIds(next);
    }

    function toggleMenuRowAll(row: MenuMatrixRow) {
        const rowIds = Object.values(row.permissions).map((perm) => perm.id);
        if (rowIds.length === 0) {
            return;
        }

        const allSelected = rowIds.every((id) => selectedIds.has(id));
        const next = new Set(selectedIds);

        if (allSelected) {
            rowIds.forEach((id) => next.delete(id));
        } else {
            rowIds.forEach((id) => next.add(id));
        }

        setPermissionIds(next);
    }

    function toggleGlobalPermission(permissionId: number, checked: boolean) {
        const next = new Set(selectedIds);

        if (checked) {
            next.add(permissionId);
        } else {
            next.delete(permissionId);
        }

        setPermissionIds(next);
    }

    function toggleAllGlobalPermissions() {
        const globalIds = matrix.global_permissions.map((perm) => perm.id);
        if (globalIds.length === 0) {
            return;
        }

        const allSelected = globalIds.every((id) => selectedIds.has(id));
        const next = new Set(selectedIds);

        if (allSelected) {
            globalIds.forEach((id) => next.delete(id));
        } else {
            globalIds.forEach((id) => next.add(id));
        }

        setPermissionIds(next);
    }

    function handleSubmit(event: FormEvent) {
        event.preventDefault();

        if (isEditing && role) {
            form.put(updateRoleRoute.url(role.id), {
                preserveScroll: true,
            });
            return;
        }

        form.post(storeRoleRoute.url(), {
            preserveScroll: true,
        });
    }

    const allErrorMessages = Object.values(form.errors).filter(Boolean);
    const pageTitle = isEditing ? `Ubah Peran: ${role.label}` : 'Buat Peran Baru';
    const allGlobalSelected =
        matrix.global_permissions.length > 0 &&
        matrix.global_permissions.every((perm) => selectedIds.has(perm.id));

    return (
        <>
            <Head title={pageTitle} />

            <form
                onSubmit={handleSubmit}
                className="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 py-6 sm:px-6"
            >
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <Link
                            href={rolesIndexRoute.url()}
                            className="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Kembali ke Daftar Peran
                        </Link>
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="text-2xl font-semibold tracking-tight">{pageTitle}</h1>
                            {role?.is_system && (
                                <Badge variant="secondary">
                                    <Lock aria-hidden="true" />
                                    Peran Bawaan
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Tentukan identitas peran lalu pilih izin per menu (Tier 1) dan izin lintas modul (Tier 2).
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" className="min-h-11 sm:min-h-9">
                            <Link href={rolesIndexRoute.url()}>Batal</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="min-h-11 sm:min-h-9"
                        >
                            {form.processing && <Spinner />}
                            {isEditing ? 'Simpan Perubahan' : 'Simpan Peran'}
                        </Button>
                    </div>
                </header>

                {allErrorMessages.length > 0 && (
                    <AlertError
                        title="Periksa kembali isian formulir"
                        errors={allErrorMessages}
                    />
                )}

                {/* Identity section */}
                <section className="space-y-4 rounded-lg border bg-card p-5">
                    <div>
                        <h2 className="text-base font-semibold">Identitas Peran</h2>
                        <p className="text-sm text-muted-foreground">
                            {role?.is_system
                                ? 'Kode peran bawaan sistem dikunci, namun label tampilan dan hak aksesnya tetap dapat disesuaikan.'
                                : 'Kosongkan kode peran untuk membuatnya otomatis dari nama peran.'}
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="role-label">Nama Peran</Label>
                            <Input
                                id="role-label"
                                name="label"
                                value={form.data.label}
                                onChange={(e) => form.setData('label', e.target.value)}
                                placeholder="Contoh: Operator Kepegawaian"
                                required
                            />
                            <InputError message={form.errors.label} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="role-name">Kode Peran (Slug)</Label>
                            <Input
                                id="role-name"
                                name="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="operator_kepegawaian"
                                disabled={isEditing}
                                className="font-mono text-sm"
                            />
                            <InputError message={form.errors.name} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="role-description">Deskripsi Singkat</Label>
                        <Input
                            id="role-description"
                            name="description"
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="Jelaskan tanggung jawab peran ini dalam instansi"
                        />
                        <InputError message={form.errors.description} />
                    </div>
                </section>

                {/* Tier 1: Menu-bound permissions */}
                <section className="space-y-4">
                    <div className="space-y-1">
                        <h2 className="text-base font-semibold">
                            Tier 1: Hak Akses Menu
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Mengaktifkan aksi selain Lihat (Tambah, Ubah, Hapus, Ekspor, Impor) akan otomatis mengaktifkan izin Lihat pada menu tersebut.
                        </p>
                    </div>

                    {matrix.menu_rows.length === 0 ? (
                        <div className="rounded-lg border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                            Belum ada daftar menu di database instansi. Jalankan perintah sinkronisasi menu terlebih dahulu.
                        </div>
                    ) : (
                        <>
                            {/* Desktop matrix table */}
                            <div className="hidden overflow-x-auto rounded-lg border md:block">
                                <table className="w-full border-collapse text-left text-sm">
                                    <thead className="border-b bg-muted/40 text-xs font-medium text-muted-foreground">
                                        <tr>
                                            <th scope="col" className="min-w-56 px-4 py-3">Menu</th>
                                            {matrix.actions.map((action) => (
                                                <th
                                                    key={action.key}
                                                    scope="col"
                                                    className="px-3 py-3 text-center"
                                                >
                                                    {action.label}
                                                </th>
                                            ))}
                                            <th scope="col" className="px-4 py-3 text-right">
                                                Baris
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {matrix.menu_rows.map((row) => {
                                            const rowPermissions = Object.values(row.permissions);
                                            const allRowChecked =
                                                rowPermissions.length > 0 &&
                                                rowPermissions.every((perm) => selectedIds.has(perm.id));

                                            return (
                                                <tr
                                                    key={row.id}
                                                    className={
                                                        row.is_group
                                                            ? 'bg-muted/20 font-medium'
                                                            : 'transition-colors hover:bg-muted/20'
                                                    }
                                                >
                                                    <td className="px-4 py-3">
                                                        <div
                                                            className={
                                                                row.parent_id !== null
                                                                    ? 'pl-4 text-foreground'
                                                                    : 'font-medium text-foreground'
                                                            }
                                                        >
                                                            {row.label}
                                                        </div>
                                                    </td>

                                                    {matrix.actions.map((action) => {
                                                        const perm = row.permissions[action.key];
                                                        if (!perm) {
                                                            return (
                                                                <td
                                                                    key={action.key}
                                                                    className="px-3 py-3 text-center text-muted-foreground/40"
                                                                >
                                                                    &ndash;
                                                                </td>
                                                            );
                                                        }

                                                        const isChecked = selectedIds.has(perm.id);
                                                        const inputId = `perm-${perm.id}`;

                                                        return (
                                                            <td key={action.key} className="px-3 py-3 text-center">
                                                                <div className="inline-flex items-center justify-center">
                                                                    <Checkbox
                                                                        id={inputId}
                                                                        checked={isChecked}
                                                                        onCheckedChange={(checked) =>
                                                                            toggleMenuPermission(
                                                                                row,
                                                                                action.key,
                                                                                checked === true,
                                                                            )
                                                                        }
                                                                        aria-label={`${action.label} ${row.label}`}
                                                                    />
                                                                </div>
                                                            </td>
                                                        );
                                                    })}

                                                    <td className="px-4 py-3 text-right">
                                                        {rowPermissions.length > 0 && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => toggleMenuRowAll(row)}
                                                            >
                                                                {allRowChecked ? 'Kosongkan' : 'Pilih semua'}
                                                            </Button>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile stacked permission cards */}
                            <div className="grid gap-3 md:hidden">
                                {matrix.menu_rows.map((row) => {
                                    const rowPermissions = Object.values(row.permissions);
                                    if (rowPermissions.length === 0) {
                                        return null;
                                    }

                                    const allRowChecked = rowPermissions.every((perm) =>
                                        selectedIds.has(perm.id),
                                    );

                                    return (
                                        <div
                                            key={row.id}
                                            className="rounded-lg border bg-card p-4"
                                        >
                                            <div className="mb-3 flex items-center justify-between gap-2">
                                                <div>
                                                    {row.parent_label && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {row.parent_label}
                                                        </div>
                                                    )}
                                                    <div className="font-medium text-foreground">
                                                        {row.label}
                                                    </div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => toggleMenuRowAll(row)}
                                                    className="min-h-10"
                                                >
                                                    {allRowChecked ? 'Kosongkan' : 'Semua'}
                                                </Button>
                                            </div>

                                            <div className="grid grid-cols-2 gap-2">
                                                {matrix.actions.map((action) => {
                                                    const perm = row.permissions[action.key];
                                                    if (!perm) {
                                                        return null;
                                                    }

                                                    const mobileId = `mobile-perm-${perm.id}`;

                                                    return (
                                                        <label
                                                            key={action.key}
                                                            htmlFor={mobileId}
                                                            className="flex min-h-11 items-center gap-2.5 rounded-md border px-3 py-2 text-sm"
                                                        >
                                                            <Checkbox
                                                                id={mobileId}
                                                                checked={selectedIds.has(perm.id)}
                                                                onCheckedChange={(checked) =>
                                                                    toggleMenuPermission(
                                                                        row,
                                                                        action.key,
                                                                        checked === true,
                                                                    )
                                                                }
                                                            />
                                                            <span>{action.label}</span>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </>
                    )}
                </section>

                {/* Tier 2: Global permissions */}
                <section className="space-y-4">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="space-y-1">
                            <h2 className="text-base font-semibold">
                                Tier 2: Izin Lintas Modul (Global)
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Hak akses khusus yang berlaku lintas menu, seperti bypass geofence atau approval lintas unit.
                            </p>
                        </div>

                        {matrix.global_permissions.length > 0 && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={toggleAllGlobalPermissions}
                                className="min-h-10 self-start sm:min-h-8 sm:self-auto"
                            >
                                {allGlobalSelected ? 'Kosongkan Izin Global' : 'Pilih Semua Global'}
                            </Button>
                        )}
                    </div>

                    {matrix.global_permissions.length === 0 ? (
                        <div className="rounded-lg border border-dashed px-6 py-8 text-center text-sm text-muted-foreground">
                            Belum ada izin global terdaftar. Jalankan seeder RBAC untuk memuat izin standar.
                        </div>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {matrix.global_permissions.map((permission) => {
                                const globalId = `global-perm-${permission.id}`;
                                const checked = selectedIds.has(permission.id);

                                return (
                                    <label
                                        key={permission.id}
                                        htmlFor={globalId}
                                        className="flex min-h-14 items-start gap-3 rounded-lg border bg-card p-3.5 transition-colors hover:bg-muted/30"
                                    >
                                        <Checkbox
                                            id={globalId}
                                            checked={checked}
                                            onCheckedChange={(value) =>
                                                toggleGlobalPermission(
                                                    permission.id,
                                                    value === true,
                                                )
                                            }
                                            className="mt-0.5"
                                        />
                                        <div className="min-w-0">
                                            <div className="text-sm font-medium text-foreground">
                                                {permission.label}
                                            </div>
                                            <div className="mt-0.5 truncate font-mono text-xs text-muted-foreground">
                                                {permission.name}
                                            </div>
                                        </div>
                                    </label>
                                );
                            })}
                        </div>
                    )}
                </section>

                <footer className="flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">
                        Total izin dipilih: <span className="font-medium text-foreground">{form.data.permission_ids.length}</span>
                    </p>

                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" className="min-h-11 sm:min-h-9">
                            <Link href={rolesIndexRoute.url()}>Batal</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="min-h-11 sm:min-h-9"
                        >
                            {form.processing && <Spinner />}
                            {isEditing ? 'Simpan Perubahan' : 'Simpan Peran'}
                        </Button>
                    </div>
                </footer>
            </form>
        </>
    );
}

RoleEditor.layout = {
    breadcrumbs: [
        {
            title: 'Peran dan Hak Akses',
            href: rolesIndexRoute(),
        },
        {
            title: 'Editor Peran',
            href: createRoleRoute(),
        },
    ],
};
