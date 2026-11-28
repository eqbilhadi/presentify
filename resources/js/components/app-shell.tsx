import { usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

const MANAGED_THEME_VARIABLES = [
    '--background',
    '--foreground',
    '--card',
    '--card-foreground',
    '--popover',
    '--popover-foreground',
    '--primary',
    '--primary-foreground',
    '--secondary',
    '--secondary-foreground',
    '--muted',
    '--muted-foreground',
    '--accent',
    '--accent-foreground',
    '--border',
    '--input',
    '--ring',
    '--radius',
    '--chart-1',
    '--chart-2',
    '--chart-3',
    '--chart-4',
    '--chart-5',
    '--sidebar',
    '--sidebar-foreground',
    '--sidebar-primary',
    '--sidebar-primary-foreground',
    '--sidebar-accent',
    '--sidebar-accent-foreground',
    '--sidebar-border',
    '--sidebar-ring',
    '--font-sans',
    '--font-heading',
] as const;

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const { sidebarOpen, tenancy } = usePage().props;
    const faviconUrl = tenancy?.current?.favicon_url;
    const tenantTheme = tenancy?.current?.theme;

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

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        const root = document.documentElement;
        document.getElementById('tenant-theme-initial')?.remove();

        const fontUrl = tenantTheme?.font_stylesheet_url ?? null;
        const existingFontLink = document.getElementById(
            'tenant-theme-fonts',
        ) as HTMLLinkElement | null;

        if (fontUrl) {
            if (existingFontLink) {
                if (existingFontLink.href !== fontUrl) {
                    existingFontLink.href = fontUrl;
                }
            } else {
                const fontLink = document.createElement('link');
                fontLink.id = 'tenant-theme-fonts';
                fontLink.rel = 'stylesheet';
                fontLink.href = fontUrl;
                document.head.appendChild(fontLink);
            }
        } else {
            existingFontLink?.remove();
        }

        const dataAttrs = tenantTheme?.data_attributes ?? null;
        if (dataAttrs) {
            root.setAttribute('data-theme-style', dataAttrs.style);
            root.setAttribute('data-menu-style', dataAttrs.menu_style);
            root.setAttribute('data-menu-accent', dataAttrs.menu_accent);
        } else {
            root.removeAttribute('data-theme-style');
            root.removeAttribute('data-menu-style');
            root.removeAttribute('data-menu-accent');
        }

        const applyThemeVariables = () => {
            const isDark = root.classList.contains('dark');
            const activeVars = isDark
                ? (tenantTheme?.dark_css_variables ?? {})
                : (tenantTheme?.css_variables ?? {});

            for (const key of MANAGED_THEME_VARIABLES) {
                const value = activeVars[key];
                if (typeof value === 'string' && value.trim() !== '') {
                    root.style.setProperty(key, value);
                } else {
                    root.style.removeProperty(key);
                }
            }
        };

        applyThemeVariables();

        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.attributeName === 'class') {
                    applyThemeVariables();
                    break;
                }
            }
        });

        observer.observe(root, {
            attributes: true,
            attributeFilter: ['class'],
        });

        return () => {
            observer.disconnect();
        };
    }, [tenantTheme]);

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    return (
        <SidebarProvider defaultOpen={sidebarOpen}>{children}</SidebarProvider>
    );
}
