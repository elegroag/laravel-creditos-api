<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
        <!-- Contenedor principal responsive -->
        <div :class="[
            'w-full space-y-8 lg:space-y-10',
            // Diferentes anchos máximos según la ruta
            currentRoute.includes('adviser') ? 'max-w-md lg:max-w-lg xl:max-w-xl' :
            currentRoute.includes('registro') ? 'max-w-4xl lg:max-w-5xl xl:max-w-6xl' :
            'max-w-md lg:max-w-lg xl:max-w-xl'
        ]">
            <!-- Header responsive -->
            <div class="text-center">
                <!-- Logo/Icon opcional -->
                <div class="mx-auto h-20 w-30 lg:h-32 lg:w-48 mb-8">
                    <img src="/assets/img/credito-social.png" alt="Comfaca Crédito" class="h-full w-full object-contain">
                </div>
                
                <!-- Títulos responsive -->
                <h1 class="text-2xl lg:text-3xl xl:text-4xl font-bold text-gray-900 mb-2 lg:mb-3">
                    Comfaca Crédito
                </h1>
                <p class="text-sm lg:text-base text-gray-600 px-4 lg:px-0">
                    {{ getSubtitle() }}
                </p>
            </div>

            <!-- Card responsive -->      
            <slot />

            <!-- Footer con información de Comfaca -->
            <div class="text-center space-y-4">
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 text-sm lg:text-base">
                    <span class="text-gray-600">¿Necesitas más información?</span>
                    <a 
                        href="https://www.comfaca.com" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 font-medium text-blue-600 hover:text-blue-500 transition-colors"
                    >
                        <svg class="h-4 w-4 lg:h-5 lg:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        Visitar Comfaca
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Obtener la ruta actual desde Inertia
const page = usePage();

// Computed para obtener la ruta actual
const currentRoute = computed(() => {
    return page.props.url || '';
});

// Función para obtener el subtítulo según la ruta
const getSubtitle = () => {
    if (currentRoute.value.includes('adviser')) {
        return 'Portal exclusivo para asesores';
    } else if (currentRoute.value.includes('registro')) {
        return 'Crea tu cuenta en el sistema';
    } else {
        return 'Acceso y registro al sistema';
    }
};
</script>
