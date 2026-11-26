export type MenuNode = {
    id: string;
    parent_id: string | null;
    sort_num: number;
    icon: string | null;
    label_name: string;
    route_name: string | null;
    url: string | null;
    permission_prefix: string | null;
    is_divider: boolean;
    children: MenuNode[];
};

export type MenuListItem = {
    id: string;
    parent_id: string | null;
    parent_label: string | null;
    sort_num: number;
    icon: string | null;
    label_name: string;
    controller_name: string | null;
    route_name: string | null;
    url: string | null;
    permission_prefix: string | null;
    is_active: boolean;
    is_divider: boolean;
    actions_count: number;
    roles_count: number;
};

export type MenuFormRecord = {
    id: string;
    parent_id: string | null;
    sort_num: number;
    icon: string | null;
    label_name: string;
    controller_name: string | null;
    route_name: string | null;
    url: string | null;
    permission_prefix: string | null;
    is_active: boolean;
    is_divider: boolean;
};

export type MenuParentOption = {
    id: string;
    label_name: string;
};

export type MenuSortNode = {
    id: string;
    parent_id: string | null;
    sort_num: number;
    icon: string | null;
    label_name: string;
    is_active: boolean;
    is_divider: boolean;
    children: MenuSortNode[];
};

export type MenuActionItem = {
    id: string;
    menu_id: string;
    action: string;
    label: string;
    permission_name: string;
    route_name: string | null;
    order: number;
};

export type MenuActionsGroup = {
    id: string;
    label_name: string;
    parent_label: string | null;
    permission_prefix: string | null;
    is_active: boolean;
    actions: MenuActionItem[];
};

export type StandalonePermission = {
    id: string;
    name: string;
    guard_name: string;
    group: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedPermissions = {
    data: StandalonePermission[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
};

export type RoleSummary = {
    id: string;
    name: string;
    guard_name: string;
    users_count: number;
    menus_count: number;
    permissions_count: number;
    is_protected: boolean;
};

export type RoleDetail = {
    id: string;
    name: string;
    is_protected: boolean;
    menu_ids: string[];
    menu_action_ids: string[];
    permission_ids: string[];
};

export type RoleMatrixAction = {
    id: string;
    action: string;
    label: string;
    permission_name: string;
    permission_id: string | null;
};

export type RoleMatrixMenu = {
    id: string;
    label_name: string;
    icon: string | null;
    is_active: boolean;
    actions: RoleMatrixAction[];
    children: RoleMatrixMenu[];
};

export type RoleMatrixStandaloneGroup = {
    group: string;
    permissions: Array<{
        id: string;
        name: string;
        group: string | null;
    }>;
};

export type RoleMatrixData = {
    menus: RoleMatrixMenu[];
    standalone_groups: RoleMatrixStandaloneGroup[];
};
