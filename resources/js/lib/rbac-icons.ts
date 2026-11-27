import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    BarChart2,
    BookOpen,
    Building2,
    Calendar,
    CheckSquare,
    Clock,
    FileText,
    Fingerprint,
    Folder,
    KeyRound,
    LayoutGrid,
    ListTree,
    Network,
    Palette,
    Settings,
    Shield,
    UserCheck,
    UserCog,
    Users,
} from 'lucide-react';

const ICON_REGISTRY: Record<string, LucideIcon> = {
    Activity,
    BarChart2,
    BookOpen,
    Building2,
    Calendar,
    CheckSquare,
    Clock,
    FileText,
    Fingerprint,
    Folder,
    KeyRound,
    LayoutGrid,
    ListTree,
    Network,
    Palette,
    Settings,
    Shield,
    UserCheck,
    UserCog,
    Users,
};

export const AVAILABLE_MENU_ICONS = Object.keys(ICON_REGISTRY);

export function resolveMenuIcon(name: string | null | undefined): LucideIcon {
    if (!name) {
        return Folder;
    }

    return ICON_REGISTRY[name] ?? Folder;
}
