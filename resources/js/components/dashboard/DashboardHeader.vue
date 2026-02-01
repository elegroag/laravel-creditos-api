<template>
    <header class="flex h-16 items-center justify-between border-b border-border bg-card px-4 sm:px-6">
        <div class="flex items-center gap-4">
            <Button
                variant="ghost"
                size="icon"
                @click="sidebarOpen = !sidebarOpen"
                class="lg:hidden text-foreground hover:bg-accent"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </Button>
            <Button
                variant="ghost"
                size="icon"
                @click="sidebarCollapsed = !sidebarCollapsed"
                class="hidden lg:flex text-foreground hover:bg-accent"
            >
                <svg v-if="sidebarCollapsed" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                </svg>
                <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                </svg>
            </Button>
            <div class="flex items-center gap-2">
                <Link href="/" class="text-sm font-medium text-muted-foreground hover:text-foreground">
                    Comfaca Crédito
                </Link>
                <span class="text-muted-foreground">/</span>
                <span class="text-sm font-semibold text-foreground">{{ sectionTitle }}</span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="hidden text-sm text-muted-foreground sm:block">
                {{ user?.username || 'Usuario' }} <span class="text-muted-foreground/60">·</span>
                <span class="text-foreground">{{ (user?.roles || []).join(', ') || 'sin roles' }}</span>
            </span>

            <div class="relative">
                <Button
                    variant="ghost"
                    class="relative h-9 w-9 rounded-full border border-border/50 p-0 hover:bg-accent focus-visible:ring-1 focus-visible:ring-ring"
                    @click="userMenuOpen = !userMenuOpen"
                >
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-xs ring-offset-background transition-colors hover:bg-primary/20">
                        {{ _abbr(user?.username || 'Usuario') }}
                    </div>
                </Button>

                <div
                    v-if="userMenuOpen"
                    class="absolute right-0 mt-2 w-64 origin-top-right rounded-xl border border-border bg-popover p-1 text-popover-foreground shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 animate-in fade-in zoom-in-95 duration-200"
                >
                    <Link
                        href="/perfil"
                        class="relative flex w-full cursor-pointer select-none items-center rounded-lg px-3 py-2 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground group"
                        @click="userMenuOpen = false"
                    >
                        <svg class="mr-2 h-4 w-4 text-muted-foreground group-hover:text-accent-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span class="font-medium">Mi Perfil</span>
                    </Link>

                    <div class="h-px bg-border/60 mx-1 my-1"></div>

                    <button
                        class="relative flex w-full cursor-pointer select-none items-center rounded-lg px-3 py-2 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground group"
                        @click="handleLogout"
                    >
                        <svg class="mr-2 h-4 w-4 text-muted-foreground group-hover:text-accent-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span class="font-medium">Cerrar sesión</span>
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import { useLayout } from '@/composables/layout/useLayout';
import { useSession } from '@/composables/useSession';

const { userMenuOpen, sidebarOpen, sidebarCollapsed } = useLayout();
const { user, clearSession } = useSession();

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

const sectionTitle = computed(() => {
    const path = window.location.pathname;
    const navItems = [
        { label: 'Inicio', to: '/web/inicio' },
        { label: 'Simulador', to: '/web/simulador' },
        { label: 'Solicitud', to: '/solicitud' },
        { label: 'Mis Solicitudes', to: '/mis-solicitudes' },
        { label: 'Documentos', to: '/documentos' },
        { label: 'Administración', to: '/admin' }
    ];

    const current = navItems.find(item =>
        (item.to === '/web/inicio' && (path === '/web/inicio' || path === '/web/dashboard' || path === '/')) ||
        (item.to !== '/web/inicio' && path.startsWith(item.to))
    );

    return current?.label || 'Inicio';
});

const handleLogout = async () => {
    userMenuOpen.value = false;
    try {
        await router.get('logout');
        clearSession();
        router.visit('login');
    } catch (error) {
        console.error('Error al cerrar sesión:', error);
    }
};
</script>
