import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { resolveMenuIcon } from '@/lib/rbac-icons';
import type { MenuNode } from '@/types';

type Props = {
    menus: MenuNode[];
};

export function NavMain({ menus }: Props) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    if (menus.length === 0) {
        return null;
    }

    const groups: Array<{
        id: string;
        label: string | null;
        items: MenuNode[];
    }> = [];

    let currentGroup: { id: string; label: string | null; items: MenuNode[] } =
        {
            id: 'root',
            label: 'Navigasi',
            items: [],
        };

    for (const node of menus) {
        if (node.is_divider) {
            if (currentGroup.items.length > 0) {
                groups.push(currentGroup);
            }
            currentGroup = {
                id: node.id,
                label: node.label_name,
                items: [],
            };
        } else {
            currentGroup.items.push(node);
        }
    }

    if (currentGroup.items.length > 0) {
        groups.push(currentGroup);
    }

    return (
        <>
            {groups.map((group, groupIndex) => (
                <SidebarGroup key={group.id} className="px-2 py-1">
                    {groupIndex > 0 && (
                        <SidebarSeparator className="mx-0 mb-2" />
                    )}
                    {group.label && (
                        <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                    )}
                    <SidebarMenu>
                        {group.items.map((item) => {
                            const Icon = resolveMenuIcon(item.icon);
                            const hasChildren = item.children.length > 0;

                            if (hasChildren) {
                                const childActive = item.children.some(
                                    (child) =>
                                        child.url !== null &&
                                        isCurrentOrParentUrl(child.url),
                                );

                                return (
                                    <Collapsible
                                        key={item.id}
                                        asChild
                                        defaultOpen={childActive}
                                        className="group/collapsible"
                                    >
                                        <SidebarMenuItem>
                                            <CollapsibleTrigger asChild>
                                                <SidebarMenuButton
                                                    tooltip={{
                                                        children:
                                                            item.label_name,
                                                    }}
                                                    isActive={childActive}
                                                >
                                                    <Icon aria-hidden="true" />
                                                    <span>
                                                        {item.label_name}
                                                    </span>
                                                    <ChevronRight
                                                        className="ml-auto transition-transform duration-150 group-data-[state=open]/collapsible:rotate-90"
                                                        aria-hidden="true"
                                                    />
                                                </SidebarMenuButton>
                                            </CollapsibleTrigger>
                                            <CollapsibleContent>
                                                <SidebarMenuSub>
                                                    {item.children.map(
                                                        (child) => {
                                                            const ChildIcon =
                                                                resolveMenuIcon(
                                                                    child.icon,
                                                                );
                                                            const childHref =
                                                                child.url;
                                                            const active =
                                                                childHref !==
                                                                    null &&
                                                                isCurrentOrParentUrl(
                                                                    childHref,
                                                                );

                                                            return (
                                                                <SidebarMenuSubItem
                                                                    key={
                                                                        child.id
                                                                    }
                                                                >
                                                                    {childHref ? (
                                                                        <SidebarMenuSubButton
                                                                            asChild
                                                                            isActive={
                                                                                active
                                                                            }
                                                                        >
                                                                            <Link
                                                                                href={
                                                                                    childHref
                                                                                }
                                                                                prefetch
                                                                            >
                                                                                <ChildIcon aria-hidden="true" />
                                                                                <span>
                                                                                    {
                                                                                        child.label_name
                                                                                    }
                                                                                </span>
                                                                            </Link>
                                                                        </SidebarMenuSubButton>
                                                                    ) : (
                                                                        <SidebarMenuSubButton
                                                                            aria-disabled="true"
                                                                            className="opacity-60"
                                                                        >
                                                                            <ChildIcon aria-hidden="true" />
                                                                            <span>
                                                                                {
                                                                                    child.label_name
                                                                                }
                                                                            </span>
                                                                        </SidebarMenuSubButton>
                                                                    )}
                                                                </SidebarMenuSubItem>
                                                            );
                                                        },
                                                    )}
                                                </SidebarMenuSub>
                                            </CollapsibleContent>
                                        </SidebarMenuItem>
                                    </Collapsible>
                                );
                            }

                            const targetUrl = item.url;
                            const active =
                                targetUrl !== null &&
                                (targetUrl === '/dashboard'
                                    ? isCurrentUrl(targetUrl)
                                    : isCurrentOrParentUrl(targetUrl));

                            return (
                                <SidebarMenuItem key={item.id}>
                                    {targetUrl ? (
                                        <SidebarMenuButton
                                            asChild
                                            isActive={active}
                                            tooltip={{
                                                children: item.label_name,
                                            }}
                                        >
                                            <Link href={targetUrl} prefetch>
                                                <Icon aria-hidden="true" />
                                                <span>{item.label_name}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    ) : (
                                        <SidebarMenuButton
                                            tooltip={{
                                                children: item.label_name,
                                            }}
                                            aria-disabled="true"
                                            className="opacity-60"
                                        >
                                            <Icon aria-hidden="true" />
                                            <span>{item.label_name}</span>
                                        </SidebarMenuButton>
                                    )}
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
