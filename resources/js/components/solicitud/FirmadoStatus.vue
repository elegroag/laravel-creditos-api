<template>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h2 class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Firma Digital
            </h2>

            <!-- Mensaje de estado -->
            <div v-if="mensajeEstado" class="alert" :class="alertClass">
                <span class="material-icons text-2xl">{{ mensajeEstado.icono }}</span>
                <div>
                    <div class="font-bold">{{ mensajeEstado.titulo }}</div>
                    <div class="text-xs">{{ mensajeEstado.descripcion }}</div>
                </div>
            </div>

            <!-- Progreso de firmado -->
            <div v-if="enProceso" class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span>Progreso de firmado</span>
                    <span class="font-bold">{{ porcentajeCompletado }}%</span>
                </div>
                <progress class="progress progress-primary w-full" :value="porcentajeCompletado" max="100"></progress>
                <div class="text-xs text-base-content/70">
                    {{ estadoActual?.firmantes_completados || 0 }} de {{ (estadoActual?.firmantes_completados || 0) + (estadoActual?.firmantes_pendientes || 0) }} firmantes han completado
                </div>
            </div>

            <!-- Estado completado -->
            <div v-if="firmadoCompleto" class="alert alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <div class="font-bold">Documento firmado</div>
                    <div class="text-xs">El proceso de firma se completó exitosamente</div>
                </div>
            </div>

            <!-- Error -->
            <div v-if="error" class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ error }}</span>
            </div>

            <!-- Acciones -->
            <div class="card-actions justify-end gap-2">
                <button
                    v-if="!procesoFirmado && !loading"
                    class="btn btn-primary"
                    @click="handleIniciarFirmado"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                    Iniciar Firmado
                </button>

                <button
                    v-if="enProceso"
                    class="btn btn-ghost"
                    :disabled="loading"
                    @click="handleActualizarEstado"
                >
                    <svg v-if="!loading" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span class="loading loading-spinner loading-sm" v-else></span>
                    Actualizar Estado
                </button>

                <button
                    v-if="puedeReintentar"
                    class="btn btn-warning"
                    @click="handleReiniciarFirmado"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Reintentar Firmado
                </button>
            </div>

            <!-- Auto-actualización -->
            <div v-if="autoActualizar && enProceso" class="text-xs text-base-content/50 text-center">
                Actualizando automáticamente cada {{ intervaloPolling / 1000 }}s
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
interface Props {
    solicitudId: string;
    autoActualizar?: boolean;
    intervaloPolling?: number;
}

const props = withDefaults(defineProps<Props>(), {
    autoActualizar: true,
    intervaloPolling: 10000 // 10 segundos
});

const emit = defineEmits<{
    firmadoIniciado: [data: any];
    firmadoCompletado: [];
    firmadoRechazado: [];
    estadoActualizado: [estado: string];
}>();

// Estado local del componente
const loading = ref(false);
const error = ref<string | null>(null);
const procesoFirmado = ref<any>(null);
const estadoActual = ref<any>(null);
const enProceso = ref(false);
const firmadoCompleto = ref(false);
const firmadoRechazado = ref(false);
const porcentajeCompletado = ref(0);
const mensajeEstado = ref<any>(null);
const puedeReintentar = ref(false);

let pollingIntervalId: number | null = null;

const alertClass = computed(() => {
    if (!mensajeEstado.value) return '';
    
    switch (mensajeEstado.value.tipo) {
        case 'success':
            return 'alert-success';
        case 'warning':
            return 'alert-warning';
        case 'error':
            return 'alert-error';
        case 'info':
        default:
            return 'alert-info';
    }
});

// Métodos simulados (deben conectarse a API real)
const iniciarFirmado = async (solicitudId: string) => {
    loading.value = true;
    error.value = null;
    
    try {
        // Simular llamada API
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        procesoFirmado.value = { id: 'firmado_123', solicitudId };
        enProceso.value = true;
        porcentajeCompletado.value = 0;
        
        return true;
    } catch (err) {
        error.value = 'Error al iniciar firmado';
        return false;
    } finally {
        loading.value = false;
    }
};

const consultarEstado = async (solicitudId: string) => {
    try {
        // Simular llamada API
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Simular progreso
        porcentajeCompletado.value = Math.min(porcentajeCompletado.value + 10, 100);
        
        if (porcentajeCompletado.value >= 100) {
            firmadoCompleto.value = true;
            enProceso.value = false;
            return 'FIRMADO';
        }
        
        return 'EN_PROCESO';
    } catch (err) {
        error.value = 'Error al consultar estado';
        return null;
    }
};

const handleIniciarFirmado = async () => {
    const exito = await iniciarFirmado(props.solicitudId);
    if (exito) {
        emit('firmadoIniciado', procesoFirmado.value);
        
        if (props.autoActualizar) {
            startPolling();
        }
    }
};

const handleActualizarEstado = async () => {
    const estado = await consultarEstado(props.solicitudId);
    if (estado) {
        emit('estadoActualizado', estado);
    }
};

const handleReiniciarFirmado = async () => {
    stopPolling();
    await handleIniciarFirmado();
};

const startPolling = () => {
    if (pollingIntervalId) return;
    
    pollingIntervalId = setInterval(async () => {
        const estado = await consultarEstado(props.solicitudId);
        if (estado) {
            emit('estadoActualizado', estado);
            
            if (estado === 'FIRMADO') {
                emit('firmadoCompletado');
                stopPolling();
            } else if (estado === 'RECHAZADO') {
                emit('firmadoRechazado');
                stopPolling();
            }
        }
    }, props.intervaloPolling);
};

const stopPolling = () => {
    if (pollingIntervalId) {
        clearInterval(pollingIntervalId);
        pollingIntervalId = null;
    }
};

onMounted(async () => {
    await handleActualizarEstado();
    
    if (props.autoActualizar && enProceso.value) {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});
</script>
