import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { AVAILABLE_MENU_ICONS, resolveMenuIcon } from '@/lib/rbac-icons';
import {
    index as menusIndexRoute,
    store as storeMenuRoute,
    update as updateMenuRoute,
} from '@/routes/rbac/menus';
import type { MenuFormRecord, MenuParentOption } from '@/types';

type Props = {
    menu: MenuFormRecord | null;
    parentOptions: MenuParentOption[];
};

const NONE_VALUE = '__none__';

export default function MenuForm({ menu, parentOptions }: Props) {
    const isEditing = menu !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        parent_id: menu?.parent_id ?? '',
        sort_num: menu?.sort_num ?? 10,
        icon: menu?.icon ?? '',
        label_name: menu?.label_name ?? '',
        controller_name: menu?.controller_name ?? '',
        route_name: menu?.route_name ?? '',
        url: menu?.url ?? '',
        permission_prefix: menu?.permission_prefix ?? '',
        is_active: menu?.is_active ?? true,
        is_divider: menu?.is_divider ?? false,
    });

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();

        if (isEditing && menu) {
            put(updateMenuRoute.url(menu.id));
            return;
        }

        post(storeMenuRoute.url());
    }

    const PreviewIcon = resolveMenuIcon(data.icon);

    return (
        <>
            <Head title={isEditing ? 'Ubah Menu' : 'Tambah Menu'} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-col gap-3 border-b pb-5">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-2 w-fit text-muted-foreground"
                        asChild
                    >
                        <Link href={menusIndexRoute.url()}>
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Kembali ke Manajemen Menu
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {isEditing
                                ? `Ubah Menu: ${menu.label_name}`
                                : 'Tambah Menu Baru'}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Atur properti tampilan sidebar dan prefix izin untuk
                            menu pada konteks aktif.
                        </p>
                    </div>
                </header>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-col gap-5 rounded-lg border p-5 sm:p-6"
                >
                    <div className="flex items-start gap-3 rounded-md border bg-muted/30 p-3.5">
                        <Checkbox
                            id="is_divider"
                            checked={data.is_divider}
                            onCheckedChange={(checked) =>
                                setData('is_divider', checked === true)
                            }
                        />
                        <div className="space-y-0.5">
                            <Label
                                htmlFor="is_divider"
                                className="cursor-pointer font-medium"
                            >
                                Jadikan Pemisah Grup (Divider)
                            </Label>
                            <p className="text-xs text-muted-foreground">
                                Divider hanya menampilkan label grup pemisah di
                                sidebar tanpa tautan atau aksi izin.
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="label_name">Label Menu</Label>
                            <Input
                                id="label_name"
                                value={data.label_name}
                                onChange={(e) =>
                                    setData('label_name', e.target.value)
                                }
                                placeholder="Contoh: Jadwal Kerja"
                                required
                            />
                            <InputError message={errors.label_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="sort_num">Urutan (Sort)</Label>
                            <Input
                                id="sort_num"
                                type="number"
                                min={0}
                                value={data.sort_num}
                                onChange={(e) =>
                                    setData(
                                        'sort_num',
                                        Number(e.target.value) || 0,
                                    )
                                }
                                className="tabular-nums"
                                required
                            />
                            <InputError message={errors.sort_num} />
                        </div>
                    </div>

                    {!data.is_divider && (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="parent_id">
                                        Menu Induk
                                    </Label>
                                    <Select
                                        value={data.parent_id || NONE_VALUE}
                                        onValueChange={(value) =>
                                            setData(
                                                'parent_id',
                                                value === NONE_VALUE
                                                    ? ''
                                                    : value,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="parent_id">
                                            <SelectValue placeholder="Menu Utama (Root)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE_VALUE}>
                                                Menu Utama (Root)
                                            </SelectItem>
                                            {parentOptions.map((option) => (
                                                <SelectItem
                                                    key={option.id}
                                                    value={option.id}
                                                >
                                                    {option.label_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.parent_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="icon">Ikon Lucide</Label>
                                    <div className="flex items-center gap-2">
                                        <span
                                            className="flex size-9 shrink-0 items-center justify-center rounded-md border bg-muted/40"
                                            aria-hidden="true"
                                        >
                                            <PreviewIcon className="size-4" />
                                        </span>
                                        <Select
                                            value={data.icon || NONE_VALUE}
                                            onValueChange={(value) =>
                                                setData(
                                                    'icon',
                                                    value === NONE_VALUE
                                                        ? ''
                                                        : value,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="icon"
                                                className="flex-1"
                                            >
                                                <SelectValue placeholder="Pilih ikon" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NONE_VALUE}>
                                                    Default (Folder)
                                                </SelectItem>
                                                {AVAILABLE_MENU_ICONS.map(
                                                    (iconName) => (
                                                        <SelectItem
                                                            key={iconName}
                                                            value={iconName}
                                                        >
                                                            {iconName}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <InputError message={errors.icon} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="url">URL Path</Label>
                                    <Input
                                        id="url"
                                        value={data.url}
                                        onChange={(e) =>
                                            setData('url', e.target.value)
                                        }
                                        placeholder="/rbac/menus"
                                        className="font-mono text-sm"
                                    />
                                    <InputError message={errors.url} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="route_name">
                                        Nama Route Laravel
                                    </Label>
                                    <Input
                                        id="route_name"
                                        value={data.route_name}
                                        onChange={(e) =>
                                            setData(
                                                'route_name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="rbac.menus.index"
                                        className="font-mono text-sm"
                                    />
                                    <InputError message={errors.route_name} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="permission_prefix">
                                        Prefix Izin
                                    </Label>
                                    <Input
                                        id="permission_prefix"
                                        value={data.permission_prefix}
                                        onChange={(e) =>
                                            setData(
                                                'permission_prefix',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="menus"
                                        className="font-mono text-sm"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Aksi menu akan membentuk izin{' '}
                                        <span className="font-mono">
                                            {data.permission_prefix || 'prefix'}
                                            .view
                                        </span>
                                        .
                                    </p>
                                    <InputError
                                        message={errors.permission_prefix}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="controller_name">
                                        Controller (Opsional)
                                    </Label>
                                    <Input
                                        id="controller_name"
                                        value={data.controller_name}
                                        onChange={(e) =>
                                            setData(
                                                'controller_name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="MenuController"
                                        className="font-mono text-sm"
                                    />
                                    <InputError
                                        message={errors.controller_name}
                                    />
                                </div>
                            </div>
                        </>
                    )}

                    <div className="flex items-center gap-2.5 pt-1">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        <Label
                            htmlFor="is_active"
                            className="cursor-pointer font-normal"
                        >
                            Menu aktif dan dapat ditampilkan di sidebar
                        </Label>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t pt-4">
                        <Button variant="outline" asChild disabled={processing}>
                            <Link href={menusIndexRoute.url()}>Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {isEditing ? 'Simpan Perubahan' : 'Simpan Menu'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
