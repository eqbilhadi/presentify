import { Head, Link, useForm } from '@inertiajs/react';
import * as CheckboxPrimitive from '@radix-ui/react-checkbox';
import { ArrowLeft, Check, Minus } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import {
    index as rolesIndexRoute,
    store as storeRoleRoute,
    update as updateRoleRoute,
} from '@/routes/rbac/roles';
import type { RoleDetail, RoleMatrixData, RoleMatrixMenu } from '@/types';

type Props = {
    role: RoleDetail | null;
    matrix: RoleMatrixData;
};

type CheckState = 'checked' | 'indeterminate' | 'unchecked';

function TriStateCheckbox({
    id,
    state,
    onToggle,
    ariaLabel,
}: {
    id: string;
    state: CheckState;
    onToggle: () => void;
    ariaLabel?: string;
}) {
    const checkedValue: boolean | 'indeterminate' =
        state === 'checked'
            ? true
            : state === 'indeterminate'
              ? 'indeterminate'
              : false;

    return (
        <CheckboxPrimitive.Root
            id={id}
            checked={checkedValue}
            onCheckedChange={onToggle}
            aria-label={ariaLabel}
            className={cn(
                'peer flex size-4 shrink-0 items-center justify-center rounded-[4px] border border-input shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
                state !== 'unchecked' &&
                    'border-primary bg-primary text-primary-foreground',
            )}
        >
            <CheckboxPrimitive.Indicator className="flex items-center justify-center text-current">
                {state === 'indeterminate' ? (
                    <Minus className="size-3" aria-hidden="true" />
                ) : (
                    <Check className="size-3.5" aria-hidden="true" />
                )}
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
}

function collectSubtreeIds(menu: RoleMatrixMenu): {
    menuIds: string[];
    actionIds: string[];
} {
    const menuIds = [menu.id, ...menu.children.map((c) => c.id)];
    const actionIds = [
        ...menu.actions.map((a) => a.id),
        ...menu.children.flatMap((c) => c.actions.map((a) => a.id)),
    ];

    return { menuIds, actionIds };
}

function resolveAggregateState(total: number, selected: number): CheckState {
    if (total === 0 || selected === 0) {
        return 'unchecked';
    }

    return selected === total ? 'checked' : 'indeterminate';
}

export default function RoleForm({ role, matrix }: Props) {
    const isEditing = role !== null;

    const standalonePermissionIds = new Set(
        matrix.standalone_groups.flatMap((group) =>
            group.permissions.map((perm) => perm.id),
        ),
    );

    const initialStandaloneIds = (role?.permission_ids ?? []).filter((id) =>
        standalonePermissionIds.has(id),
    );

    const { data, setData, post, put, processing, errors } = useForm({
        name: role?.name ?? '',
        menu_ids: role?.menu_ids ?? ([] as string[]),
        menu_action_ids: role?.menu_action_ids ?? ([] as string[]),
        permission_ids: initialStandaloneIds,
    });

    const selectedMenus = new Set(data.menu_ids);
    const selectedActions = new Set(data.menu_action_ids);
    const selectedStandalone = new Set(data.permission_ids);

    function getRootState(root: RoleMatrixMenu): CheckState {
        const { menuIds, actionIds } = collectSubtreeIds(root);
        const total = menuIds.length + actionIds.length;
        const selected =
            menuIds.filter((id) => selectedMenus.has(id)).length +
            actionIds.filter((id) => selectedActions.has(id)).length;

        return resolveAggregateState(total, selected);
    }

    function getMenuNodeState(menu: RoleMatrixMenu): CheckState {
        const actionIds = menu.actions.map((a) => a.id);
        const total = 1 + actionIds.length;
        const selected =
            (selectedMenus.has(menu.id) ? 1 : 0) +
            actionIds.filter((id) => selectedActions.has(id)).length;

        return resolveAggregateState(total, selected);
    }

    function getAllActionsState(menu: RoleMatrixMenu): CheckState {
        const actionIds = menu.actions.map((a) => a.id);
        return resolveAggregateState(
            actionIds.length,
            actionIds.filter((id) => selectedActions.has(id)).length,
        );
    }

    function toggleRootSubtree(root: RoleMatrixMenu) {
        const { menuIds, actionIds } = collectSubtreeIds(root);
        const current = getRootState(root);
        const nextMenus = new Set(selectedMenus);
        const nextActions = new Set(selectedActions);

        if (current === 'checked') {
            menuIds.forEach((id) => nextMenus.delete(id));
            actionIds.forEach((id) => nextActions.delete(id));
        } else {
            menuIds.forEach((id) => nextMenus.add(id));
            actionIds.forEach((id) => nextActions.add(id));
        }

        setData((prev) => ({
            ...prev,
            menu_ids: Array.from(nextMenus),
            menu_action_ids: Array.from(nextActions),
        }));
    }

    function toggleChildNode(root: RoleMatrixMenu, child: RoleMatrixMenu) {
        const current = getMenuNodeState(child);
        const actionIds = child.actions.map((a) => a.id);
        const nextMenus = new Set(selectedMenus);
        const nextActions = new Set(selectedActions);

        if (current === 'checked') {
            nextMenus.delete(child.id);
            actionIds.forEach((id) => nextActions.delete(id));
        } else {
            nextMenus.add(root.id);
            nextMenus.add(child.id);
            actionIds.forEach((id) => nextActions.add(id));
        }

        setData((prev) => ({
            ...prev,
            menu_ids: Array.from(nextMenus),
            menu_action_ids: Array.from(nextActions),
        }));
    }

    function toggleAllMenuActions(
        menu: RoleMatrixMenu,
        parentRoot?: RoleMatrixMenu,
    ) {
        const current = getAllActionsState(menu);
        const actionIds = menu.actions.map((a) => a.id);
        const nextMenus = new Set(selectedMenus);
        const nextActions = new Set(selectedActions);

        if (current === 'checked') {
            actionIds.forEach((id) => nextActions.delete(id));
        } else {
            nextMenus.add(menu.id);
            if (parentRoot) {
                nextMenus.add(parentRoot.id);
            }
            actionIds.forEach((id) => nextActions.add(id));
        }

        setData((prev) => ({
            ...prev,
            menu_ids: Array.from(nextMenus),
            menu_action_ids: Array.from(nextActions),
        }));
    }

    function toggleSingleAction(
        menu: RoleMatrixMenu,
        actionId: string,
        parentRoot?: RoleMatrixMenu,
    ) {
        const nextMenus = new Set(selectedMenus);
        const nextActions = new Set(selectedActions);

        if (nextActions.has(actionId)) {
            nextActions.delete(actionId);
        } else {
            nextActions.add(actionId);
            nextMenus.add(menu.id);
            if (parentRoot) {
                nextMenus.add(parentRoot.id);
            }
        }

        setData((prev) => ({
            ...prev,
            menu_ids: Array.from(nextMenus),
            menu_action_ids: Array.from(nextActions),
        }));
    }

    function toggleStandaloneGroup(groupPermissionIds: string[]) {
        const selectedCount = groupPermissionIds.filter((id) =>
            selectedStandalone.has(id),
        ).length;
        const allSelected = selectedCount === groupPermissionIds.length;
        const next = new Set(selectedStandalone);

        if (allSelected) {
            groupPermissionIds.forEach((id) => next.delete(id));
        } else {
            groupPermissionIds.forEach((id) => next.add(id));
        }

        setData('permission_ids', Array.from(next));
    }

    function toggleStandalonePermission(permissionId: string) {
        const next = new Set(selectedStandalone);
        if (next.has(permissionId)) {
            next.delete(permissionId);
        } else {
            next.add(permissionId);
        }
        setData('permission_ids', Array.from(next));
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (isEditing && role) {
            put(updateRoleRoute.url(role.id));
            return;
        }

        post(storeRoleRoute.url());
    }

    return (
        <>
            <Head title={isEditing ? 'Ubah Peran' : 'Tambah Peran'} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-3 border-b pb-5">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-2 w-fit text-muted-foreground"
                        asChild
                    >
                        <Link href={rolesIndexRoute.url()}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Kembali ke Daftar Peran
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {isEditing
                                ? `Ubah Peran: ${role.name}`
                                : 'Buat Peran Baru'}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Tentukan nama peran lalu centang akses pohon menu,
                            aksi granular, dan izin global standalone.
                        </p>
                    </div>
                </header>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    <section className="rounded-lg border p-5">
                        <div className="grid max-w-md gap-2">
                            <Label htmlFor="role-name">Slug Nama Peran</Label>
                            <Input
                                id="role-name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="hr_supervisor"
                                className="font-mono text-sm"
                                disabled={role?.is_protected}
                                required
                            />
                            {role?.is_protected && (
                                <p className="text-xs text-muted-foreground">
                                    Slug peran superadmin_saas dilindungi dan
                                    tidak dapat diubah.
                                </p>
                            )}
                            <InputError message={errors.name} />
                        </div>
                    </section>

                    <section className="flex flex-col gap-3 rounded-lg border p-5">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-3">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Matriks Pohon Menu &amp; Aksi
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Centang induk untuk seluruh submenu, atau
                                    pilih aksi tertentu per menu.
                                </p>
                            </div>
                            <Badge variant="outline" className="tabular-nums">
                                {data.menu_ids.length} menu &middot;{' '}
                                {data.menu_action_ids.length} aksi
                            </Badge>
                        </div>

                        {matrix.menus.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Belum ada menu aktif untuk dikonfigurasi.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-3">
                                {matrix.menus.map((root) => {
                                    const rootState = getRootState(root);
                                    const rootAllActionsState =
                                        getAllActionsState(root);

                                    return (
                                        <div
                                            key={root.id}
                                            className="rounded-md border bg-card"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-3 bg-muted/30 px-4 py-3">
                                                <div className="flex items-center gap-2.5">
                                                    <TriStateCheckbox
                                                        id={`menu-root-${root.id}`}
                                                        state={rootState}
                                                        onToggle={() =>
                                                            toggleRootSubtree(
                                                                root,
                                                            )
                                                        }
                                                        ariaLabel={`Pilih seluruh akses ${root.label_name}`}
                                                    />
                                                    <Label
                                                        htmlFor={`menu-root-${root.id}`}
                                                        className="cursor-pointer font-semibold"
                                                    >
                                                        {root.label_name}
                                                    </Label>
                                                    {!root.is_active && (
                                                        <Badge variant="secondary">
                                                            Nonaktif
                                                        </Badge>
                                                    )}
                                                </div>

                                                {root.actions.length > 0 && (
                                                    <div className="flex flex-wrap items-center gap-4">
                                                        <div className="flex items-center gap-1.5">
                                                            <TriStateCheckbox
                                                                id={`menu-all-actions-${root.id}`}
                                                                state={
                                                                    rootAllActionsState
                                                                }
                                                                onToggle={() =>
                                                                    toggleAllMenuActions(
                                                                        root,
                                                                    )
                                                                }
                                                            />
                                                            <Label
                                                                htmlFor={`menu-all-actions-${root.id}`}
                                                                className="cursor-pointer text-xs text-muted-foreground"
                                                            >
                                                                Semua Aksi
                                                            </Label>
                                                        </div>

                                                        {root.actions.map(
                                                            (action) => (
                                                                <div
                                                                    key={
                                                                        action.id
                                                                    }
                                                                    className="flex items-center gap-1.5"
                                                                >
                                                                    <TriStateCheckbox
                                                                        id={`action-${action.id}`}
                                                                        state={
                                                                            selectedActions.has(
                                                                                action.id,
                                                                            )
                                                                                ? 'checked'
                                                                                : 'unchecked'
                                                                        }
                                                                        onToggle={() =>
                                                                            toggleSingleAction(
                                                                                root,
                                                                                action.id,
                                                                            )
                                                                        }
                                                                    />
                                                                    <Label
                                                                        htmlFor={`action-${action.id}`}
                                                                        className="cursor-pointer text-xs"
                                                                    >
                                                                        {
                                                                            action.label
                                                                        }
                                                                    </Label>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            </div>

                                            {root.children.length > 0 && (
                                                <div className="divide-y border-t">
                                                    {root.children.map(
                                                        (child) => {
                                                            const childState =
                                                                getMenuNodeState(
                                                                    child,
                                                                );
                                                            const childActionsState =
                                                                getAllActionsState(
                                                                    child,
                                                                );

                                                            return (
                                                                <div
                                                                    key={
                                                                        child.id
                                                                    }
                                                                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 pl-9"
                                                                >
                                                                    <div className="flex items-center gap-2.5">
                                                                        <TriStateCheckbox
                                                                            id={`menu-child-${child.id}`}
                                                                            state={
                                                                                childState
                                                                            }
                                                                            onToggle={() =>
                                                                                toggleChildNode(
                                                                                    root,
                                                                                    child,
                                                                                )
                                                                            }
                                                                        />
                                                                        <Label
                                                                            htmlFor={`menu-child-${child.id}`}
                                                                            className="cursor-pointer text-sm font-medium"
                                                                        >
                                                                            {
                                                                                child.label_name
                                                                            }
                                                                        </Label>
                                                                    </div>

                                                                    {child
                                                                        .actions
                                                                        .length >
                                                                        0 && (
                                                                        <div className="flex flex-wrap items-center gap-4">
                                                                            <div className="flex items-center gap-1.5">
                                                                                <TriStateCheckbox
                                                                                    id={`menu-all-actions-${child.id}`}
                                                                                    state={
                                                                                        childActionsState
                                                                                    }
                                                                                    onToggle={() =>
                                                                                        toggleAllMenuActions(
                                                                                            child,
                                                                                            root,
                                                                                        )
                                                                                    }
                                                                                />
                                                                                <Label
                                                                                    htmlFor={`menu-all-actions-${child.id}`}
                                                                                    className="cursor-pointer text-xs text-muted-foreground"
                                                                                >
                                                                                    Semua
                                                                                </Label>
                                                                            </div>

                                                                            {child.actions.map(
                                                                                (
                                                                                    action,
                                                                                ) => (
                                                                                    <div
                                                                                        key={
                                                                                            action.id
                                                                                        }
                                                                                        className="flex items-center gap-1.5"
                                                                                    >
                                                                                        <TriStateCheckbox
                                                                                            id={`action-${action.id}`}
                                                                                            state={
                                                                                                selectedActions.has(
                                                                                                    action.id,
                                                                                                )
                                                                                                    ? 'checked'
                                                                                                    : 'unchecked'
                                                                                            }
                                                                                            onToggle={() =>
                                                                                                toggleSingleAction(
                                                                                                    child,
                                                                                                    action.id,
                                                                                                    root,
                                                                                                )
                                                                                            }
                                                                                        />
                                                                                        <Label
                                                                                            htmlFor={`action-${action.id}`}
                                                                                            className="cursor-pointer text-xs"
                                                                                        >
                                                                                            {
                                                                                                action.label
                                                                                            }
                                                                                        </Label>
                                                                                    </div>
                                                                                ),
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </section>

                    <section className="flex flex-col gap-3 rounded-lg border p-5">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-3">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Standalone Permissions
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    Hak akses global lintas modul yang tidak
                                    terikat pada menu tertentu.
                                </p>
                            </div>
                            <Badge variant="outline" className="tabular-nums">
                                {data.permission_ids.length} dipilih
                            </Badge>
                        </div>

                        {matrix.standalone_groups.length === 0 ? (
                            <p className="py-4 text-center text-sm text-muted-foreground">
                                Tidak ada standalone permission pada konteks
                                ini.
                            </p>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2">
                                {matrix.standalone_groups.map((grp) => {
                                    const ids = grp.permissions.map(
                                        (p) => p.id,
                                    );
                                    const grpState = resolveAggregateState(
                                        ids.length,
                                        ids.filter((id) =>
                                            selectedStandalone.has(id),
                                        ).length,
                                    );

                                    return (
                                        <div
                                            key={grp.group}
                                            className="rounded-md border p-3.5"
                                        >
                                            <div className="mb-2.5 flex items-center gap-2 border-b pb-2">
                                                <TriStateCheckbox
                                                    id={`sg-${grp.group}`}
                                                    state={grpState}
                                                    onToggle={() =>
                                                        toggleStandaloneGroup(
                                                            ids,
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`sg-${grp.group}`}
                                                    className="cursor-pointer text-sm font-medium"
                                                >
                                                    {grp.group}
                                                </Label>
                                            </div>

                                            <div className="flex flex-col gap-2">
                                                {grp.permissions.map((perm) => (
                                                    <div
                                                        key={perm.id}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <TriStateCheckbox
                                                            id={`sp-${perm.id}`}
                                                            state={
                                                                selectedStandalone.has(
                                                                    perm.id,
                                                                )
                                                                    ? 'checked'
                                                                    : 'unchecked'
                                                            }
                                                            onToggle={() =>
                                                                toggleStandalonePermission(
                                                                    perm.id,
                                                                )
                                                            }
                                                        />
                                                        <Label
                                                            htmlFor={`sp-${perm.id}`}
                                                            className="cursor-pointer font-mono text-xs"
                                                        >
                                                            {perm.name}
                                                        </Label>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </section>

                    <div className="flex items-center justify-end gap-3">
                        <Button variant="outline" asChild disabled={processing}>
                            <Link href={rolesIndexRoute.url()}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {isEditing ? 'Simpan Perubahan' : 'Buat Peran'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
