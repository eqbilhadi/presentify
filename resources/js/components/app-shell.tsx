import { usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const { sidebarOpen, tenancy } = usePage().props;
    const faviconUrl = tenancy?.current?.favicon_url;

    useEffect(() => {
        if (typeof document === 'undefined' || !faviconUrl) {
            return;
        }

        let iconLink = document.querySelector<HTMLLinkElement>(
            'link[rel="icon"][data-tenant-favicon]',
        );

        if (!iconLink) {
            iconLink = document.createElement('link');
            iconLink.rel = 'icon';
            iconLink.setAttribute('data-tenant-favicon', 'true');
            document.head.appendChild(iconLink);
        }

        iconLink.href = faviconUrl;
    }, [faviconUrl]);

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    return (
        <SidebarProvider defaultOpen={sidebarOpen}>{children}</SidebarProvider>
    );
}
