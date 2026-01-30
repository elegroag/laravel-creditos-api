// Exportar todos los layouts desde un solo lugar
export { default as AuthLayout } from './AuthLayout.vue';
export { default as DashboardLayout } from './DashboardLayout.vue';
export { default as DefaultLayout } from './DefaultLayout.vue';

// Exportaciones por defecto para compatibilidad
export { default } from './AuthLayout.vue';

// Tipos para layouts
export interface LayoutProps {
    title?: string;
    description?: string;
    showHeader?: boolean;
    showSidebar?: boolean;
    showFooter?: boolean;
}

export interface AuthLayoutProps extends LayoutProps {
    showLogo?: boolean;
    subtitle?: string;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
}

export interface DashboardLayoutProps extends LayoutProps {
    sidebarCollapsed?: boolean;
    userMenuOpen?: boolean;
    breadcrumbs?: Array<{
        label: string;
        href?: string;
    }>;
}

export interface DefaultLayoutProps extends LayoutProps {
    centered?: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    showBackLink?: boolean;
    backLinkText?: string;
    backLinkHref?: string;
}
