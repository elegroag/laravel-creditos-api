<template>
    <div class="flex h-screen overflow-hidden bg-background">
        <DashboardSidebar />

        <!-- Main Content -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <DashboardHeader />

            <main class="flex-1 overflow-y-auto bg-background p-4 sm:p-6">
                <slot />
            </main>
            
            <!-- Overlay para cerrar menú de usuario al hacer click fuera -->
            <div v-if="userMenuOpen" class="fixed inset-0 z-40" @click="userMenuOpen = false"></div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import DashboardSidebar from '@/components/dashboard/DashboardSidebar.vue';
import DashboardHeader from '@/components/dashboard/DashboardHeader.vue';
import { useLayout } from '@/composables/layout/useLayout';

const page = usePage();
const { userMenuOpen } = useLayout();

// Computed para obtener información del usuario
const currentUser = computed(() => {
    return page.props.auth?.user || null;
});

// Computed para obtener la ruta actual
const currentRoute = computed(() => {
    return page.props.url || '';
});
</script>
