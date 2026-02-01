<template>
    <!-- Sidebar Desktop -->
    <aside :class="sidebarDesktopClasses">
        <div class="flex h-16 items-center gap-3 border-b border-sidebar-border px-6">
            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground font-bold text-sm">
                CC
            </div>
            <span v-if="!sidebarCollapsed" class="text-lg font-semibold text-sidebar-foreground">Comfaca Crédito</span>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4">
            <div class="space-y-1">
                <Link
                    v-for="item in navItems"
                    :key="item.to"
                    :href="item.to"
                    :class="getMenuItemClasses(item.to)"
                    :title="sidebarCollapsed ? item.label : undefined"
                >
                    <span class="h-5 w-5 shrink-0">{{ item.icon || '📄' }}</span>
                    <span v-show="!sidebarCollapsed">{{ item.label }}</span>
                </Link>
            </div>
        </nav>

        <div class="border-t border-sidebar-border p-4">
            <div class="flex items-center gap-3 rounded-lg bg-sidebar-accent/50 px-3 py-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-sidebar-primary text-sidebar-primary-foreground font-semibold text-sm">
                    {{ _abbr(user?.username || 'Usuario') }}
                </div>
                <div v-show="!sidebarCollapsed" class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium text-sidebar-foreground">{{ user?.username || 'Usuario' }}</div>
                    <div class="truncate text-xs text-sidebar-foreground/60">{{ getPrimaryRoleDisplay }}</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-40 bg-black/50 lg:hidden"
        @click="sidebarOpen = false"
    />

    <!-- Mobile Sidebar -->
    <aside :class="sidebarMobileClasses">
        <div class="flex h-16 items-center justify-between border-b border-sidebar-border px-6">
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground font-bold text-sm">
                    CC
                </div>
                <span class="text-lg font-semibold text-sidebar-foreground">Comfaca Crédito</span>
            </div>
            <Button variant="ghost" size="icon" @click="sidebarOpen = false" class="text-sidebar-foreground">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </Button>
        </div>

        <nav class="overflow-y-auto px-3 py-4">
            <div class="space-y-1">
                <Link
                    v-for="item in navItems"
                    :key="item.to"
                    :href="item.to"
                    @click="sidebarOpen = false"
                    :class="getMobileMenuItemClasses(item.to)"
                >
                    <span class="h-5 w-5">{{ item.icon || '📄' }}</span>
                    {{ item.label }}
                </Link>
            </div>
        </nav>
    </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import Button from '@/components/ui/Button.vue';
import { useLayout } from '@/composables/layout/useLayout';
import { useSession } from '@/composables/useSession';

const { sidebarOpen, sidebarCollapsed, navItems } = useLayout();
const { user } = useSession();

const _abbr = (label: string): string => {
    const parts = label
        .split(' ')
        .map((s) => s.trim())
        .filter(Boolean);

    const abbr = parts
        .slice(0, 2)
        .map((p) => p[0] || '')
        .join('')
        .toUpperCase();

    return abbr || (label.trim()[0] || '').toUpperCase() || '·';
};

const getPrimaryRoleDisplay = computed(() => {
    const roles = user.value?.roles || [];
    if (roles.includes('admin')) return 'Administrador';
    if (roles.includes('adviser')) return 'Asesor';
    if (roles.includes('user_trabajador')) return 'Trabajador';
    if (roles.includes('user_empresa')) return 'Empresa';
    return 'Usuario';
});

const sidebarDesktopClasses = computed(() => {
    const baseClasses = 'hidden h-screen flex-col border-r border-sidebar-border bg-sidebar transition-all duration-300 lg:flex shrink-0';
    const widthClass = sidebarCollapsed.value ? 'w-16' : 'w-64';
    return cn(baseClasses, widthClass);
});

const sidebarMobileClasses = computed(() => {
    const baseClasses = 'fixed inset-y-0 left-0 z-50 w-64 h-screen transform border-r border-sidebar-border bg-sidebar transition-transform duration-300 lg:hidden';
    const transformClass = sidebarOpen.value ? 'translate-x-0' : '-translate-x-full';
    return cn(baseClasses, transformClass);
});

const isActive = (to: string) => {
    const path = window.location.pathname;
    if (to === '/web/inicio') return path === '/web/inicio' || path === '/web/dashboard' || path === '/';
    return path.startsWith(to);
};

const getMenuItemClasses = (href: string) => {
    const baseClasses = 'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors';
    const activeClasses = isActive(href)
        ? 'bg-sidebar-primary text-sidebar-primary-foreground'
        : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground';
    const collapsedClass = sidebarCollapsed.value ? 'justify-center' : '';

    return cn(baseClasses, activeClasses, collapsedClass);
};

const getMobileMenuItemClasses = (href: string) => {
    const baseClasses = 'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors';
    const activeClasses = isActive(href)
        ? 'bg-sidebar-primary text-sidebar-primary-foreground'
        : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground';

    return cn(baseClasses, activeClasses);
};
</script>
