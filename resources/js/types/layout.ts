export interface NavItem {
    label: string;
    to: string;
    abbr: string;
    icon: any;
    category?: string;
    badge?: string;
    children?: NavItem[];
    requiresAuth?: boolean;
    permissions?: string[];
}

export interface LayoutConfig {
    sidebarOpen: boolean;
    sidebarCollapsed: boolean;
    userMenuOpen: boolean;
    theme: 'light' | 'dark' | 'auto';
    notifications: boolean;
}

export interface BreadcrumbItem {
    label: string;
    to?: string;
    href?: string;
    icon?: any;
}

export interface NotificationItem {
    id: string;
    type: 'info' | 'success' | 'warning' | 'error';
    title: string;
    message: string;
    timestamp: string;
    read: boolean;
    action?: {
        label: string;
        handler: () => void;
    };
}

export interface QuickAction {
    id: string;
    label: string;
    description: string;
    icon: any;
    color: string;
    handler: () => void;
    shortcut?: string;
    requiresAuth?: boolean;
}

export interface UserMenuItem {
    label: string;
    icon: any;
    handler: () => void;
    divider?: boolean;
    danger?: boolean;
}

export interface SearchState {
    query: string;
    results: Array<{
        id: string;
        title: string;
        description: string;
        type: string;
        url: string;
        icon: any;
    }>;
    loading: boolean;
    open: boolean;
}

export interface ThemeConfig {
    mode: 'light' | 'dark' | 'auto';
    primaryColor: string;
    sidebarColor: string;
    headerColor: string;
}
