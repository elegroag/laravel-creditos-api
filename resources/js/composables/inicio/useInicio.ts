import { ref, computed } from 'vue';
import { useSession } from '@/composables/useSession';
import { useApi } from '@/composables/useApi';
import type {
    SolicitudResumen,
    EstadoSolicitud,
    EstadoSolicitudData,
    DashboardStats,
    ActividadReciente,
    Notificacion,
    QuickAction,
    ResumenFinanciero
} from '@/types/inicio';

export function useInicio() {
    const { isAuthenticated } = useSession();
    const { getJson, putJson } = useApi();

    // Estado de solicitudes
    const solicitudes = ref<SolicitudResumen[]>([]);
    const loadingSolicitudes = ref(false);
    const solicitudesError = ref('');

    // Flujo de aprobación
    const flujoAprobacion = ref<EstadoSolicitud[]>([]);
    const estadosData = ref<EstadoSolicitudData[]>([]);
    const loadingEstados = ref(false);
    const estadosError = ref('');

    // Dashboard
    const dashboardStats = ref<DashboardStats | null>(null);
    const loadingStats = ref(false);
    const statsError = ref('');

    // Actividad y notificaciones
    const actividadReciente = ref<ActividadReciente[]>([]);
    const notificaciones = ref<Notificacion[]>([]);
    const loadingActivity = ref(false);
    const activityError = ref('');

    // Quick actions
    const quickActions = ref<QuickAction[]>([]);

    // Resumen financiero
    const resumenFinanciero = ref<ResumenFinanciero | null>(null);
    const loadingFinanciero = ref(false);
    const financieroError = ref('');

    // Utilidades de formateo
    const fmtMoney = (value: unknown): string => {
        const n = typeof value === 'number' ? value : Number(value);
        const v = Number.isFinite(n) ? n : 0;
        return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v);
    };

    const fmtDate = (value: unknown): string => {
        if (typeof value !== 'string' || !value) return '-';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return '-';
        return new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium' }).format(d);
    };

    const fmtDateTime = (value: unknown): string => {
        if (typeof value !== 'string' || !value) return '-';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return '-';
        return new Intl.DateTimeFormat('es-CO', {
            dateStyle: 'medium',
            timeStyle: 'short'
        }).format(d);
    };

    // Funciones de utilidad para estados
    const normalizeEstado = (estado: string): string => {
        return (estado || '')
            .trim()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    };

    const estadoIndex = (estado: string) => {
        const s = normalizeEstado(estado);
        return flujoAprobacion.value.findIndex((e: EstadoSolicitud) => normalizeEstado(e.nombre) === s);
    };

    const getEstadoInfo = (estado: string) => {
        const index = estadoIndex(estado);
        if (index !== -1) {
            return flujoAprobacion.value[index];
        }

        // Estado por defecto si no está en el flujo
        return {
            nombre: estado,
            descripcion: 'Estado desconocido',
            color: 'gray',
            icono: '❓',
            requiere_accion: false,
            duracion_promedio_dias: 0
        };
    };

    const getEstadoColor = (estado: string) => {
        return getEstadoInfo(estado).color;
    };

    const getEstadoIcono = (estado: string) => {
        return getEstadoInfo(estado).icono;
    };

    const requiereAccionUsuario = (estado: string) => {
        return getEstadoInfo(estado).requiere_accion;
    };

    const getSiguienteEstado = (estado: string) => {
        const index = estadoIndex(estado);
        if (index !== -1 && index < flujoAprobacion.value.length - 1) {
            return flujoAprobacion.value[index + 1];
        }
        return null;
    };

    const getProgresoEstado = (estado: string) => {
        const index = estadoIndex(estado);
        if (index === -1) return 0;
        return ((index + 1) / flujoAprobacion.value.length) * 100;
    };

    // Métodos principales
    const cargarSolicitudes = async () => {
        if (!isAuthenticated.value) return;

        loadingSolicitudes.value = true;
        solicitudesError.value = '';

        try {
            const response = await getJson<{
                success: boolean;
                data: SolicitudResumen[];
                message?: string;
            }>('/api/inicio/solicitudes', { auth: true });

            if (response.success) {
                solicitudes.value = response.data || [];
            } else {
                solicitudesError.value = response.message || 'Error al cargar solicitudes';
            }
        } catch (err: any) {
            console.error('Error cargando solicitudes:', err);
            solicitudesError.value = err.message || 'Error al cargar las solicitudes';
        } finally {
            loadingSolicitudes.value = false;
        }
    };

    const cargarFlujoAprobacion = async () => {
        loadingEstados.value = true;
        estadosError.value = '';

        try {
            const response = await getJson<{
                success: boolean;
                data: {
                    flujo: EstadoSolicitud[];
                    estadisticas: EstadoSolicitudData[];
                };
                message?: string;
            }>('/api/inicio/flujo-aprobacion', { auth: true });

            if (response.success) {
                flujoAprobacion.value = response.data.flujo || [];
                estadosData.value = response.data.estadisticas || [];
            } else {
                estadosError.value = response.message || 'Error al cargar flujo de aprobación';
            }
        } catch (err: any) {
            console.error('Error cargando flujo de aprobación:', err);
            estadosError.value = err.message || 'Error al cargar el flujo de aprobación';
        } finally {
            loadingEstados.value = false;
        }
    };

    const cargarDashboardStats = async () => {
        if (!isAuthenticated.value) return;

        loadingStats.value = true;
        statsError.value = '';

        try {
            const response = await getJson<{
                success: boolean;
                data: DashboardStats;
                message?: string;
            }>('/api/inicio/dashboard-stats', { auth: true });

            if (response.success) {
                dashboardStats.value = response.data;
            } else {
                statsError.value = response.message || 'Error al cargar estadísticas';
            }
        } catch (err: any) {
            console.error('Error cargando estadísticas:', err);
            statsError.value = err.message || 'Error al cargar las estadísticas';
        } finally {
            loadingStats.value = false;
        }
    };

    const cargarActividadReciente = async () => {
        if (!isAuthenticated.value) return;

        loadingActivity.value = true;
        activityError.value = '';

        try {
            const response = await getJson<{
                success: boolean;
                data: {
                    actividad: ActividadReciente[];
                    notificaciones: Notificacion[];
                };
                message?: string;
            }>('/api/inicio/actividad', { auth: true });

            if (response.success) {
                actividadReciente.value = response.data.actividad || [];
                notificaciones.value = response.data.notificaciones || [];
            } else {
                activityError.value = response.message || 'Error al cargar actividad';
            }
        } catch (err: any) {
            console.error('Error cargando actividad:', err);
            activityError.value = err.message || 'Error al cargar la actividad';
        } finally {
            loadingActivity.value = false;
        }
    };

    const cargarQuickActions = async () => {
        if (!isAuthenticated.value) return;

        try {
            const response = await getJson<{
                success: boolean;
                data: QuickAction[];
            }>('/api/inicio/quick-actions', { auth: true });

            if (response.success) {
                quickActions.value = response.data || [];
            }
        } catch (err: any) {
            console.error('Error cargando acciones rápidas:', err);
        }
    };

    const cargarResumenFinanciero = async () => {
        if (!isAuthenticated.value) return;

        loadingFinanciero.value = true;
        financieroError.value = '';

        try {
            const response = await getJson<{
                success: boolean;
                data: ResumenFinanciero;
                message?: string;
            }>('/api/inicio/resumen-financiero', { auth: true });

            if (response.success) {
                resumenFinanciero.value = response.data;
            } else {
                financieroError.value = response.message || 'Error al cargar resumen financiero';
            }
        } catch (err: any) {
            console.error('Error cargando resumen financiero:', err);
            financieroError.value = err.message || 'Error al cargar el resumen financiero';
        } finally {
            loadingFinanciero.value = false;
        }
    };

    const marcarNotificacionLeida = async (notificacionId: string) => {
        try {
            await putJson(`/api/inicio/notificaciones/${notificacionId}/leer`, {}, { auth: true });

            // Actualizar localmente
            const notificacion = notificaciones.value.find((n: Notificacion) => n.id === notificacionId);
            if (notificacion) {
                notificacion.leida = true;
            }
        } catch (err: any) {
            console.error('Error marcando notificación como leída:', err);
        }
    };

    const marcarTodasNotificacionesLeidas = async () => {
        try {
            await putJson('/api/inicio/notificaciones/marcar-leidas', {}, { auth: true });

            // Actualizar localmente
            notificaciones.value.forEach((n: Notificacion) => {
                n.leida = true;
            });
        } catch (err: any) {
            console.error('Error marcando todas las notificaciones como leídas:', err);
        }
    };

    // Computed properties
    const solicitudesPendientes = computed(() => {
        return solicitudes.value.filter((s: SolicitudResumen) =>
            ['pendiente', 'en_revision', 'requiere_documentacion'].includes(s.estado.toLowerCase())
        );
    });

    const solicitudesRequierenAccion = computed(() => {
        return solicitudes.value.filter((s: SolicitudResumen) => s.requiere_accion);
    });

    const notificacionesNoLeidas = computed(() => {
        return notificaciones.value.filter((n: Notificacion) => !n.leida);
    });

    const totalNotificacionesNoLeidas = computed(() => {
        return notificacionesNoLeidas.value.length;
    });

    const proximosPagos = computed(() => {
        return resumenFinanciero.value?.proximos_pagos || [];
    });

    const pagosVencidos = computed(() => {
        return proximosPagos.value.filter((p: ResumenFinanciero['proximos_pagos'][number]) => p.dias_para_vencer < 0);
    });

    const pagosPorVencer = computed(() => {
        return proximosPagos.value.filter((p: ResumenFinanciero['proximos_pagos'][number]) => p.dias_para_vencer >= 0 && p.dias_para_vencer <= 7);
    });

    const quickActionsDisponibles = computed(() => {
        return quickActions.value.filter((action: QuickAction) => action.disponible);
    });

    // Utilidades adicionales
    const getDiasEnEstado = (fecha: string) => {
        const fechaEstado = new Date(fecha);
        const hoy = new Date();
        const diffTime = Math.abs(hoy.getTime() - fechaEstado.getTime());
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    };

    const getDiasParaVencer = (fecha: string) => {
        const fechaVencimiento = new Date(fecha);
        const hoy = new Date();
        const diffTime = fechaVencimiento.getTime() - hoy.getTime();
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    };

    const getEstadoBadgeColor = (estado: string) => {
        const color = getEstadoColor(estado);
        const colores: Record<string, string> = {
            'green': 'bg-green-100 text-green-800',
            'blue': 'bg-blue-100 text-blue-800',
            'yellow': 'bg-yellow-100 text-yellow-800',
            'red': 'bg-red-100 text-red-800',
            'purple': 'bg-purple-100 text-purple-800',
            'gray': 'bg-gray-100 text-gray-800'
        };
        return colores[color] || colores.gray;
    };

    // Inicialización
    const inicializar = async () => {
        if (!isAuthenticated.value) return;

        await Promise.all([
            cargarSolicitudes(),
            cargarFlujoAprobacion(),
            cargarDashboardStats(),
            cargarActividadReciente(),
            cargarQuickActions(),
            cargarResumenFinanciero()
        ]);
    };

    return {
        // Estado
        solicitudes,
        loadingSolicitudes,
        solicitudesError,
        flujoAprobacion,
        estadosData,
        loadingEstados,
        estadosError,
        dashboardStats,
        loadingStats,
        statsError,
        actividadReciente,
        notificaciones,
        loadingActivity,
        activityError,
        quickActions,
        resumenFinanciero,
        loadingFinanciero,
        financieroError,

        // Computed
        solicitudesPendientes,
        solicitudesRequierenAccion,
        notificacionesNoLeidas,
        totalNotificacionesNoLeidas,
        proximosPagos,
        pagosVencidos,
        pagosPorVencer,
        quickActionsDisponibles,

        // Métodos principales
        cargarSolicitudes,
        cargarFlujoAprobacion,
        cargarDashboardStats,
        cargarActividadReciente,
        cargarQuickActions,
        cargarResumenFinanciero,

        // Métodos de notificaciones
        marcarNotificacionLeida,
        marcarTodasNotificacionesLeidas,

        // Utilidades de formateo
        fmtMoney,
        fmtDate,
        fmtDateTime,

        // Utilidades de estados
        getEstadoInfo,
        getEstadoColor,
        getEstadoIcono,
        requiereAccionUsuario,
        getSiguienteEstado,
        getProgresoEstado,
        getEstadoBadgeColor,

        // Utilidades adicionales
        getDiasEnEstado,
        getDiasParaVencer,

        // Inicialización
        inicializar,
    };
}
