import { Link, router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import AppLogo from '@/components/app-logo';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { Spinner } from '@/components/ui/spinner';
import { useIsMobile } from '@/hooks/use-mobile';
import { dashboard } from '@/routes';
import { switchMethod as tenantSwitch } from '@/routes/tenant/sso';
import { switchMethod as centralSwitch } from '@/routes/workspace';
import type { TenantSummary } from '@/types';

export function WorkspaceSwitcher() {
    const { name, tenancy } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const [switchingId, setSwitchingId] = useState<string | null>(null);

    const currentTenant = tenancy?.current ?? null;
    const tenants = tenancy?.tenants ?? [];

    const canSwitch =
        tenants.length > 1 || (!currentTenant && tenants.length > 0);

    function handleSwitch(target: TenantSummary) {
        if (switchingId !== null || target.id === currentTenant?.id) {
            return;
        }

        const actionUrl = currentTenant
            ? tenantSwitch.url()
            : centralSwitch.url();

        setSwitchingId(target.id);
        router.post(
            actionUrl,
            { tenant_id: target.id },
            {
                preserveScroll: false,
                onError: (errors) => {
                    const message =
                        typeof errors.tenant_id === 'string'
                            ? errors.tenant_id
                            : 'Gagal berpindah ke instansi yang dipilih.';
                    toast.error(message);
                },
                onFinish: () => {
                    setSwitchingId(null);
                },
            },
        );
    }

    if (!currentTenant && tenants.length === 0) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" asChild>
                        <Link href={dashboard()} prefetch>
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    if (!canSwitch && currentTenant) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" asChild>
                        <Link href={dashboard()} prefetch>
                            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                {currentTenant.logo_url ? (
                                    <img
                                        src={currentTenant.logo_url}
                                        alt={currentTenant.name}
                                        width={32}
                                        height={32}
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <Building2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                            </div>
                            <div className="ml-1 grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {currentTenant.name}
                                </span>
                                <span className="truncate font-mono text-xs text-muted-foreground">
                                    {currentTenant.slug}
                                </span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            aria-label="Pindah instansi"
                            data-test="workspace-switcher-trigger"
                        >
                            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                {switchingId ? (
                                    <Spinner className="size-4" />
                                ) : currentTenant?.logo_url ? (
                                    <img
                                        src={currentTenant.logo_url}
                                        alt={currentTenant.name}
                                        width={32}
                                        height={32}
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <Building2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                            </div>
                            <div className="ml-1 grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {currentTenant ? currentTenant.name : name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {currentTenant
                                        ? currentTenant.slug
                                        : 'Portal Utama'}
                                </span>
                            </div>
                            <ChevronsUpDown
                                className="ml-auto size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-60 rounded-lg"
                        align="start"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'right'
                                  : 'bottom'
                        }
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="text-xs font-medium text-muted-foreground">
                            Pilih Instansi ({tenants.length})
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />

                        {tenants.map((tenant) => {
                            const isCurrent = tenant.id === currentTenant?.id;
                            const isSwitching = switchingId === tenant.id;

                            return (
                                <DropdownMenuItem
                                    key={tenant.id}
                                    disabled={switchingId !== null || isCurrent}
                                    onSelect={() => handleSwitch(tenant)}
                                    className="flex cursor-pointer items-center gap-2.5 py-2"
                                    data-test={`workspace-option-${tenant.slug}`}
                                >
                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-md border bg-muted/50 text-foreground">
                                        {isSwitching ? (
                                            <Spinner className="size-3.5" />
                                        ) : (
                                            <Building2
                                                className="size-3.5"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col">
                                        <span className="truncate text-sm font-medium">
                                            {tenant.name}
                                        </span>
                                        <span className="truncate font-mono text-xs text-muted-foreground">
                                            {tenant.slug}
                                        </span>
                                    </span>
                                    {isCurrent && (
                                        <Check
                                            className="size-4 shrink-0 text-primary"
                                            aria-label="Aktif"
                                        />
                                    )}
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
