import { ref, computed } from 'vue';
import { useApi } from '@/composables/useApi';
import { useSession } from '@/composables/useSession';
import type { 
    FirmadoIniciarResponse, 
    FirmadoEstadoResponse, 
    EstadoFirmado,
    Firmante,
    ProcesoFirmado
} from '@/types/solicitud-credito';

export function useFirmadoDigital() {
    const { postJson, getJson } = useApi();
    const { isAuthenticated } = useSession();

    // Estado
    const loading = ref(false);
    const error = ref<string | null>(null);
    const procesoFirmado = ref<ProcesoFirmado | null>(null);
    const estadoActual = ref<FirmadoEstadoResponse['data'] | null>(null);
    const pollingInterval = ref<NodeJS.Timeout | null>(null);

    // Estado para modales
    const showFirmaModal = ref(false);
    const showEstadoModal = ref(false);
    const urlFirmaActual = ref<string>('');

    // Computed properties
    const enProceso = computed(() => estadoActual.value?.estado === 'PENDIENTE_FIRMADO');
    const firmadoCompleto = computed(() => estadoActual.value?.estado === 'FIRMADO');
    const firmadoRechazado = computed(() => estadoActual.value?.estado === 'RECHAZADO');
    const firmadoExpirado = computed(() => estadoActual.value?.estado === 'EXPIRADO');

    const porcentajeCompletado = computed(() => {
        if (!estadoActual.value) return 0;
        const total = estadoActual.value.firmantes_completados + estadoActual.value.firmantes_pendientes;
        if (total === 0) return 0;
        return Math.round((estadoActual.value.firmantes_completados / total) * 100);
    });

    const firmantesCompletados = computed(() => {
        return estadoActual.value?.firmantes?.filter(f => f.estado === 'FIRMADO') || [];
    });

    const firmantesPendientes = computed(() => {
        return estadoActual.value?.firmantes?.filter(f => f.estado === 'PENDIENTE_FIRMADO') || [];
    });

    const puedeIniciarFirmado = computed(() => {
        return isAuthenticated.value && !enProceso.value && !firmadoCompleto.value;
    });

    const mensajeEstado = computed(() => {
        if (!estadoActual.value) return '';
        
        switch (estadoActual.value.estado) {
            case 'PENDIENTE_FIRMADO':
                return 'El proceso de firma está en curso. Esperando las firmas de los participantes.';
            case 'FIRMADO':
                return '¡Todos los documentos han sido firmados exitosamente!';
            case 'RECHAZADO':
                return 'El proceso de firma ha sido rechazado.';
            case 'EXPIRADO':
                return 'El proceso de firma ha expirado.';
            default:
                return 'Estado desconocido del proceso de firma.';
        }
    });

    const colorEstado = computed(() => {
        switch (estadoActual.value?.estado) {
            case 'PENDIENTE_FIRMADO': return 'blue';
            case 'FIRMADO': return 'green';
            case 'RECHAZADO': return 'red';
            case 'EXPIRADO': return 'orange';
            default: return 'gray';
        }
    });

    // Métodos principales
    const iniciarProcesoFirmado = async (solicitudId: string, firmantes: Array<{ id: string; email: string; rol: string }>) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await postJson<FirmadoIniciarResponse>('/api/firmado/iniciar', {
                solicitud_id: solicitudId,
                firmantes: firmantes
            }, { auth: true });

            if (response.success) {
                procesoFirmado.value = {
                    solicitud_id: response.data.solicitud_id,
                    transaccion_id: response.data.transaccion_id,
                    estado: response.data.estado as EstadoFirmado,
                    firmantes: firmantes.map(f => ({
                        ...f,
                        estado: 'PENDIENTE_FIRMADO',
                        url_firma: response.data.urls_firma[f.id]
                    })),
                    urls_firma: response.data.urls_firma,
                    fecha_inicio: new Date().toISOString()
                };

                estadoActual.value = response.data;
                
                // Iniciar polling para verificar estado
                iniciarPolling(response.data.transaccion_id);
                
                return response.data;
            } else {
                throw new Error(response.message);
            }
        } catch (err: any) {
            console.error('Error iniciando proceso de firmado:', err);
            error.value = err.message || 'Error al iniciar el proceso de firma';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const verificarEstadoFirmado = async (transaccionId: string) => {
        try {
            const response = await getJson<FirmadoEstadoResponse>(`/api/firmado/estado/${transaccionId}`, { auth: true });

            if (response.success) {
                estadoActual.value = response.data;

                // Si el proceso está completo, detener el polling
                if (response.data.estado === 'FIRMADO' || 
                    response.data.estado === 'RECHAZADO' || 
                    response.data.estado === 'EXPIRADO') {
                    detenerPolling();
                }

                return response.data;
            }
        } catch (err: any) {
            console.error('Error verificando estado de firmado:', err);
            error.value = err.message || 'Error al verificar el estado de firma';
        }
    };

    const iniciarPolling = (transaccionId: string) => {
        detenerPolling(); // Detener cualquier polling existente

        pollingInterval.value = setInterval(async () => {
            await verificarEstadoFirmado(transaccionId);
        }, 5000); // Verificar cada 5 segundos
    };

    const detenerPolling = () => {
        if (pollingInterval.value) {
            clearInterval(pollingInterval.value);
            pollingInterval.value = null;
        }
    };

    const abrirUrlFirma = (firmanteId: string) => {
        const url = procesoFirmado.value?.urls_firma[firmanteId];
        if (url) {
            urlFirmaActual.value = url;
            showFirmaModal.value = true;
        } else {
            error.value = 'URL de firma no disponible para este firmante';
        }
    };

    const abrirUrlFirmaEnNuevaVentana = (firmanteId: string) => {
        const url = procesoFirmado.value?.urls_firma[firmanteId];
        if (url) {
            window.open(url, '_blank', 'width=800,height=600,scrollbars=yes');
        } else {
            error.value = 'URL de firma no disponible para este firmante';
        }
    };

    const cancelarProcesoFirmado = async (transaccionId: string) => {
        loading.value = true;
        error.value = null;

        try {
            await postJson(`/api/firmado/cancelar/${transaccionId}`, {}, { auth: true });
            
            detenerPolling();
            procesoFirmado.value = null;
            estadoActual.value = null;
        } catch (err: any) {
            console.error('Error cancelando proceso de firmado:', err);
            error.value = err.message || 'Error al cancelar el proceso de firma';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const reenviarInvitacion = async (firmanteId: string) => {
        loading.value = true;
        error.value = null;

        try {
            await postJson('/api/firmado/reenviar', {
                transaccion_id: estadoActual.value?.transaccion_id,
                firmante_id: firmanteId
            }, { auth: true });
        } catch (err: any) {
            console.error('Error reenviando invitación:', err);
            error.value = err.message || 'Error al reenviar la invitación';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // Métodos de modales
    const abrirModalFirma = (firmanteId: string) => {
        abrirUrlFirma(firmanteId);
    };

    const cerrarModalFirma = () => {
        showFirmaModal.value = false;
        urlFirmaActual.value = '';
    };

    const abrirModalEstado = () => {
        showEstadoModal.value = true;
    };

    const cerrarModalEstado = () => {
        showEstadoModal.value = false;
    };

    // Utilidades
    const formatoFecha = (fecha: string): string => {
        return new Date(fecha).toLocaleDateString('es-CO', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const getEstadoIcono = (estado: string): string => {
        const iconos: Record<string, string> = {
            'PENDIENTE_FIRMADO': '⏳',
            'FIRMADO': '✅',
            'RECHAZADO': '❌',
            'EXPIRADO': '⚠️'
        };
        return iconos[estado] || '📄';
    };

    const getEstadoLabel = (estado: string): string => {
        const labels: Record<string, string> = {
            'PENDIENTE_FIRMADO': 'Pendiente de Firma',
            'FIRMADO': 'Firmado',
            'RECHAZADO': 'Rechazado',
            'EXPIRADO': 'Expirado'
        };
        return labels[estado] || estado;
    };

    const getRolLabel = (rol: string): string => {
        const labels: Record<string, string> = {
            'solicitante': 'Solicitante',
            'codeudor': 'Codeudor',
            'asesor': 'Asesor',
            'administrador': 'Administrador'
        };
        return labels[rol] || rol;
    };

    // Limpieza
    const limpiarEstado = () => {
        detenerPolling();
        procesoFirmado.value = null;
        estadoActual.value = null;
        error.value = null;
        loading.value = false;
    };

    // Inicialización
    const inicializar = async (transaccionId?: string) => {
        if (transaccionId) {
            await verificarEstadoFirmado(transaccionId);
        }
    };

    // Cleanup al desmontar
    const cleanup = () => {
        detenerPolling();
    };

    return {
        // Estado
        loading,
        error,
        procesoFirmado,
        estadoActual,
        showFirmaModal,
        showEstadoModal,
        urlFirmaActual,

        // Computed
        enProceso,
        firmadoCompleto,
        firmadoRechazado,
        firmadoExpirado,
        porcentajeCompletado,
        firmantesCompletados,
        firmantesPendientes,
        puedeIniciarFirmado,
        mensajeEstado,
        colorEstado,

        // Métodos principales
        iniciarProcesoFirmado,
        verificarEstadoFirmado,
        cancelarProcesoFirmado,
        reenviarInvitacion,

        // Métodos de URL
        abrirUrlFirma,
        abrirUrlFirmaEnNuevaVentana,

        // Modales
        abrirModalFirma,
        cerrarModalFirma,
        abrirModalEstado,
        cerrarModalEstado,

        // Utilidades
        formatoFecha,
        getEstadoIcono,
        getEstadoLabel,
        getRolLabel,

        // Limpieza
        limpiarEstado,
        cleanup,

        // Inicialización
        inicializar,
    };
}
