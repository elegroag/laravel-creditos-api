<template>
    <Card class="border-0 shadow-md hover:shadow-lg transition-shadow duration-200 bg-white dark:bg-gray-800">
        <CardContent class="p-6">
            <h2 class="text-xl font-semibold mb-6 text-center bg-gradient-to-r from-sky-400 to-blue-500 bg-clip-text text-transparent">Estado del proceso</h2>
            
            <div class="relative">
                <div class="absolute left-8 top-0 bottom-0 w-0.5 bg-gradient-to-b from-sky-200 to-blue-200 dark:from-sky-700 dark:to-blue-700"></div>
                
                <div class="space-y-6">
                    <!-- Estados completados -->
                    <div
                        v-for="estado in estadosCompletados"
                        :key="estado.id"
                        class="flex items-start"
                    >
                        <div class="relative z-10 flex items-center justify-center w-16 h-16 bg-gradient-to-br from-emerald-100 to-green-100 dark:from-emerald-900/30 dark:to-green-900/30 rounded-full shadow-sm">
                            <svg class="h-8 w-8 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-6 flex-1">
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ estado.nombre }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ estado.descripcion }}</p>
                            <p v-if="estado.fecha" class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ estado.fecha }}</p>
                        </div>
                    </div>
                    
                    <!-- Estado actual -->
                    <div
                        v-if="estadoActual"
                        class="flex items-start"
                    >
                        <div class="relative z-10 flex items-center justify-center w-16 h-16 bg-gradient-to-br from-sky-100 to-blue-100 dark:from-sky-900/30 dark:to-blue-900/30 rounded-full shadow-sm animate-pulse">
                            <svg class="h-8 w-8 text-sky-600 dark:text-sky-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-6 flex-1">
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ estadoActual.nombre }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ estadoActual.descripcion }}</p>
                            <p v-if="estadoActual.tiempoEstimado" class="text-xs text-sky-600 dark:text-sky-400 mt-1 font-medium">
                                ⏱️ Tiempo estimado: {{ estadoActual.tiempoEstimado }}
                            </p>
                        </div>
                    </div>
                    
                    <!-- Estados futuros -->
                    <div
                        v-for="estado in estadosFuturos"
                        :key="estado.id"
                        class="flex items-start opacity-50"
                    >
                        <div class="relative z-10 flex items-center justify-center w-16 h-16 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-700 rounded-full shadow-sm">
                            <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-6 flex-1">
                            <h3 class="font-semibold text-gray-700 dark:text-gray-400">{{ estado.nombre }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-500">{{ estado.descripcion }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';

interface EstadoTimeline {
    id: string;
    nombre: string;
    descripcion: string;
    fecha?: string;
    tiempoEstimado?: string;
}

interface Props {
    estados: EstadoTimeline[];
    estadoActualId?: string;
    fechaEnvio?: string;
}

const props = withDefaults(defineProps<Props>(), {
    estadoActualId: '',
    fechaEnvio: ''
});

// Computar estados según su posición
const estadosCompletados = computed(() => {
    if (!props.estadoActualId) return [];
    
    const currentIndex = props.estados.findIndex(e => e.id === props.estadoActualId);
    if (currentIndex === -1) return [];
    
    return props.estados.slice(0, currentIndex).map(estado => ({
        ...estado,
        fecha: estado.id === 'DOCUMENTOS_CARGADOS' ? props.fechaEnvio : undefined
    }));
});

const estadoActual = computed(() => {
    if (!props.estadoActualId) return null;
    
    const estado = props.estados.find(e => e.id === props.estadoActualId);
    if (!estado) return null;
    
    return {
        ...estado,
        tiempoEstimado: getTiempoEstimado(estado.id)
    };
});

const estadosFuturos = computed(() => {
    if (!props.estadoActualId) return props.estados;
    
    const currentIndex = props.estados.findIndex(e => e.id === props.estadoActualId);
    if (currentIndex === -1) return [];
    
    return props.estados.slice(currentIndex + 1);
});

// Función para obtener tiempo estimado según el estado
const getTiempoEstimado = (estadoId: string): string => {
    const tiempos: Record<string, string> = {
        'POSTULADO': 'Inmediato',
        'DOCUMENTOS_CARGADOS': '1-2 días hábiles',
        'ENVIADO_VALIDACION': '1-2 días hábiles',
        'PENDIENTE_FIRMADO': '2-3 días hábiles',
        'FIRMADO': '1-2 días hábiles',
        'ENVIADO_PENDIENTE_APROBACION': '3-5 días hábiles',
        'APROBADO': '1-2 días hábiles',
        'DESEMBOLSADO': 'Inmediato'
    };
    
    return tiempos[estadoId] || '';
};
</script>
