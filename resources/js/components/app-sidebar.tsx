import { usePage } from '@inertiajs/react';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
import { dashboard } from '@/routes';
import type { MenuNode } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const dynamicMenus = auth?.menus ?? [];

    const menusToRender: MenuNode[] =
        dynamicMenus.length > 0
            ? dynamicMenus
            : [
                  {
                      id: 'fallback-dashboard',
                      parent_id: null,
                      sort_num: 1,
                      icon: 'LayoutGrid',
                      label_name: 'Dashboard',
                      route_name: 'dashboard',
                      url: dashboard.url(),
                      permission_prefix: 'dashboard',
                      is_divider: false,
                      children: [],
                  },
              ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <WorkspaceSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain menus={menusToRender} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
