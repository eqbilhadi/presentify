import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    CornerDownRight,
    GripVertical,
    IndentDecrease,
    IndentIncrease,
    Save,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { resolveMenuIcon } from '@/lib/rbac-icons';
import {
    index as menusIndexRoute,
    reorder as reorderMenusRoute,
} from '@/routes/rbac/menus';
import type { MenuSortNode } from '@/types';

type Props = {
    tree: MenuSortNode[];
};

export default function MenuSort({ tree: initialTree }: Props) {
    const [roots, setRoots] = useState<MenuSortNode[]>(initialTree);
    const [saving, setSaving] = useState(false);
    const [draggedId, setDraggedId] = useState<string | null>(null);

    function moveRoot(index: number, delta: -1 | 1) {
        const target = index + delta;
        if (target < 0 || target >= roots.length) {
            return;
        }

        const next = [...roots];
        const [item] = next.splice(index, 1);
        next.splice(target, 0, item);
        setRoots(next);
    }

    function moveChild(rootIndex: number, childIndex: number, delta: -1 | 1) {
        const root = roots[rootIndex];
        const target = childIndex + delta;
        if (!root || target < 0 || target >= root.children.length) {
            return;
        }

        const nextChildren = [...root.children];
        const [item] = nextChildren.splice(childIndex, 1);
        nextChildren.splice(target, 0, item);

        const next = [...roots];
        next[rootIndex] = { ...root, children: nextChildren };
        setRoots(next);
    }

    function indentRootIntoPrevious(rootIndex: number) {
        if (rootIndex <= 0) {
            return;
        }

        const current = roots[rootIndex];
        const previous = roots[rootIndex - 1];
        if (
            !current ||
            !previous ||
            current.is_divider ||
            previous.is_divider
        ) {
            return;
        }
        if (current.children.length > 0) {
            return;
        }

        const next = [...roots];
        next.splice(rootIndex, 1);
        next[rootIndex - 1] = {
            ...previous,
            children: [
                ...previous.children,
                { ...current, parent_id: previous.id, children: [] },
            ],
        };
        setRoots(next);
    }

    function outdentChildToRoot(rootIndex: number, childIndex: number) {
        const root = roots[rootIndex];
        const child = root?.children[childIndex];
        if (!root || !child) {
            return;
        }

        const nextChildren = root.children.filter((_, i) => i !== childIndex);
        const next = [...roots];
        next[rootIndex] = { ...root, children: nextChildren };
        next.splice(rootIndex + 1, 0, {
            ...child,
            parent_id: null,
            children: [],
        });
        setRoots(next);
    }

    function handleRootDrop(targetRootIndex: number) {
        if (!draggedId) {
            return;
        }

        const sourceRootIndex = roots.findIndex((r) => r.id === draggedId);
        if (sourceRootIndex !== -1 && sourceRootIndex !== targetRootIndex) {
            const next = [...roots];
            const [moved] = next.splice(sourceRootIndex, 1);
            next.splice(targetRootIndex, 0, moved);
            setRoots(next);
        }
        setDraggedId(null);
    }

    function handleSaveOrder() {
        if (saving) {
            return;
        }

        const items: Array<{
            id: string;
            parent_id: string | null;
            sort_num: number;
        }> = [];

        roots.forEach((root, rootIdx) => {
            items.push({
                id: root.id,
                parent_id: null,
                sort_num: (rootIdx + 1) * 10,
            });

            root.children.forEach((child, childIdx) => {
                items.push({
                    id: child.id,
                    parent_id: root.id,
                    sort_num: (childIdx + 1) * 10,
                });
            });
        });

        setSaving(true);
        router.post(
            reorderMenusRoute.url(),
            { items },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <>
            <Head title="Urutkan Menu" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="-ml-2 w-fit text-muted-foreground"
                            asChild
                        >
                            <Link href={menusIndexRoute.url()}>
                                <ArrowLeft
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Kembali ke Manajemen Menu
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Urutkan Struktur Menu
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Seret item menu utama atau gunakan tombol arah
                                untuk mengatur urutan dan hierarki induk-anak.
                            </p>
                        </div>
                    </div>

                    <Button
                        type="button"
                        onClick={handleSaveOrder}
                        disabled={saving || roots.length === 0}
                        className="min-h-10 sm:min-h-9"
                    >
                        {saving ? (
                            <Spinner />
                        ) : (
                            <Save className="size-4" aria-hidden="true" />
                        )}
                        Simpan Urutan
                    </Button>
                </header>

                {roots.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground">
                        Belum ada menu untuk diurutkan.
                    </div>
                ) : (
                    <ul className="flex flex-col gap-2" role="list">
                        {roots.map((root, rootIndex) => {
                            const Icon = resolveMenuIcon(root.icon);
                            const canIndent =
                                rootIndex > 0 &&
                                !root.is_divider &&
                                !roots[rootIndex - 1]?.is_divider &&
                                root.children.length === 0;

                            return (
                                <li
                                    key={root.id}
                                    draggable
                                    onDragStart={() => setDraggedId(root.id)}
                                    onDragOver={(e) => e.preventDefault()}
                                    onDrop={() => handleRootDrop(rootIndex)}
                                    className="rounded-lg border bg-card"
                                >
                                    <div className="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                        <div className="flex min-w-0 items-center gap-2.5">
                                            <GripVertical
                                                className="size-4 shrink-0 cursor-grab text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            {!root.is_divider && (
                                                <Icon
                                                    className="size-4 shrink-0 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                            )}
                                            <span className="truncate font-medium">
                                                {root.label_name}
                                            </span>
                                            {root.is_divider && (
                                                <Badge variant="outline">
                                                    Divider
                                                </Badge>
                                            )}
                                            {!root.is_active && (
                                                <Badge variant="secondary">
                                                    Nonaktif
                                                </Badge>
                                            )}
                                        </div>

                                        <div className="flex shrink-0 items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={rootIndex === 0}
                                                onClick={() =>
                                                    moveRoot(rootIndex, -1)
                                                }
                                                aria-label={`Naikkan ${root.label_name}`}
                                            >
                                                <ArrowUp
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={
                                                    rootIndex ===
                                                    roots.length - 1
                                                }
                                                onClick={() =>
                                                    moveRoot(rootIndex, 1)
                                                }
                                                aria-label={`Turunkan ${root.label_name}`}
                                            >
                                                <ArrowDown
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={!canIndent}
                                                onClick={() =>
                                                    indentRootIntoPrevious(
                                                        rootIndex,
                                                    )
                                                }
                                                aria-label={`Jadikan ${root.label_name} submenu`}
                                            >
                                                <IndentIncrease
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </div>
                                    </div>

                                    {root.children.length > 0 && (
                                        <ul
                                            className="border-t bg-muted/20 px-4 py-2"
                                            role="list"
                                        >
                                            {root.children.map(
                                                (child, childIndex) => {
                                                    const ChildIcon =
                                                        resolveMenuIcon(
                                                            child.icon,
                                                        );

                                                    return (
                                                        <li
                                                            key={child.id}
                                                            className="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-muted/40"
                                                        >
                                                            <div className="flex min-w-0 items-center gap-2">
                                                                <CornerDownRight
                                                                    className="size-3.5 shrink-0 text-muted-foreground"
                                                                    aria-hidden="true"
                                                                />
                                                                <ChildIcon
                                                                    className="size-3.5 shrink-0 text-muted-foreground"
                                                                    aria-hidden="true"
                                                                />
                                                                <span className="truncate text-sm">
                                                                    {
                                                                        child.label_name
                                                                    }
                                                                </span>
                                                                {!child.is_active && (
                                                                    <Badge variant="secondary">
                                                                        Nonaktif
                                                                    </Badge>
                                                                )}
                                                            </div>

                                                            <div className="flex shrink-0 items-center gap-1">
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    disabled={
                                                                        childIndex ===
                                                                        0
                                                                    }
                                                                    onClick={() =>
                                                                        moveChild(
                                                                            rootIndex,
                                                                            childIndex,
                                                                            -1,
                                                                        )
                                                                    }
                                                                    aria-label={`Naikkan ${child.label_name}`}
                                                                >
                                                                    <ArrowUp
                                                                        className="size-3.5"
                                                                        aria-hidden="true"
                                                                    />
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    disabled={
                                                                        childIndex ===
                                                                        root
                                                                            .children
                                                                            .length -
                                                                            1
                                                                    }
                                                                    onClick={() =>
                                                                        moveChild(
                                                                            rootIndex,
                                                                            childIndex,
                                                                            1,
                                                                        )
                                                                    }
                                                                    aria-label={`Turunkan ${child.label_name}`}
                                                                >
                                                                    <ArrowDown
                                                                        className="size-3.5"
                                                                        aria-hidden="true"
                                                                    />
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() =>
                                                                        outdentChildToRoot(
                                                                            rootIndex,
                                                                            childIndex,
                                                                        )
                                                                    }
                                                                    aria-label={`Keluarkan ${child.label_name} ke menu utama`}
                                                                >
                                                                    <IndentDecrease
                                                                        className="size-3.5"
                                                                        aria-hidden="true"
                                                                    />
                                                                </Button>
                                                            </div>
                                                        </li>
                                                    );
                                                },
                                            )}
                                        </ul>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </>
    );
}
