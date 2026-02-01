import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
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
    const page = usePage();

    // Datos desde el servidor
    const solicitudesData = computed(() => page.props.solicitudesData as any);
    const estadosData = computed(() => page.props.estadosData as any);
    const estadisticasData = computed(() => page.props.estadisticasData as any);

    // Estado de solicitudes
    const solicitudes = ref<SolicitudResumen[]>([]);
    const loadingSolicitudes = ref(false);
    const solicitudesError = ref('');

    // Flujo de aprobación
    const flujoAprobacion = ref<EstadoSolicitud[]>([]);
    const estadosDataLocal = ref<EstadoSolicitudData[]>([]);
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

    const getEstadoData = (estado: string): EstadoSolicitudData | undefined => {
        const key = normalizeEstado(estado);
        return estadosDataLocal.value.find((e: EstadoSolicitudData) => normalizeEstado(e.estado) === key);
    };

    const estadoIndex = (estado: string): number => {
        const s = normalizeEstado(estado);
        return flujoAprobacion.value.findIndex((e: EstadoSolicitud) => normalizeEstado(e.nombre) === s);
    };

    const ultimaSolicitud = computed(() => (solicitudes.value.length ? solicitudes.value[0] : null));

    const estadoIndexUltima = computed(() => {
        const s = ultimaSolicitud.value;
        if (!s) return -1;
        return estadoIndex(String((s as { estado?: string }).estado || ''));
    });

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

    const estadoProgressPercent = (estado: string): number => {
        const idx = estadoIndex(estado);
        if (idx < 0) return 0;
        if (flujoAprobacion.value.length <= 1) return 0;
        return Math.round((idx / (flujoAprobacion.value.length - 1)) * 100);
    };

    const estadoProgressClass = (estado: string): string => {
        const idx = estadoIndex(estado);
        if (idx < 0) return 'bg-zinc-300';
        if (idx <= 1) return 'bg-amber-500';
        if (idx === flujoAprobacion.value.length - 1) return 'bg-zinc-500';
        return 'bg-emerald-500';
    };

    const estadoBadgeClass = (estado: string): string => {
        const s = normalizeEstado(estado);
        if (s === 'aprobado' || s === 'activo' || s === 'desembolsado') return 'bg-emerald-50 text-emerald-800';
        if (s === 'en validacion' || s === 'postulado') return 'bg-amber-50 text-amber-800';
        if (s === 'finalizado') return 'bg-zinc-100 text-zinc-800';
        if (s === 'desiste') return 'bg-red-50 text-red-800';
        return 'bg-zinc-100 text-zinc-800';
    };

    // Métodos principales - ahora usan datos del servidor
    const cargarSolicitudes = () => {
        const data = solicitudesData.value;
        if (data?.success) {
            solicitudes.value = data.data || [];
            solicitudesError.value = '';
        } else {
            solicitudes.value = [];
            solicitudesError.value = data?.message || 'Error al cargar solicitudes';
        }
    };

    const resetSolicitudes = () => {
        solicitudes.value = [];
        solicitudesError.value = '';
    };

    const cargarFlujoAprobacion = () => {
        const data = estadosData.value;
        if (data?.success) {
            flujoAprobacion.value = data.data || [];
            estadosDataLocal.value = data.data || [];
            estadosError.value = '';
        } else {
            flujoAprobacion.value = [];
            estadosDataLocal.value = [];
            estadosError.value = data?.message || 'Error al cargar flujo de aprobación';
        }
    };

    const cargarDashboardStats = () => {
        const data = estadisticasData.value;
        if (data?.success) {
            dashboardStats.value = data.data;
            statsError.value = '';
        } else {
            dashboardStats.value = null;
            statsError.value = data?.message || 'Error al cargar estadísticas';
        }
    };

    const cargarActividadReciente = () => {
        // Por ahora, dejar vacío hasta tener datos reales de actividad
        actividadReciente.value = [];
        notificaciones.value = [];
        activityError.value = '';
    };

    const cargarQuickActions = () => {
        // Acciones rápidas basadas en el estado
        quickActions.value = [
            {
                id: 'nueva-solicitud',
                titulo: 'Nueva Solicitud',
                descripcion: 'Iniciar una nueva solicitud de crédito',
                icono: 'Plus',
                url: '/solicitud',
                color: 'primary',
                disponible: true
            },
            {
                id: 'simulador',
                titulo: 'Simulador',
                descripcion: 'Simular cuotas y plazos',
                icono: 'Calculator',
                url: '/web/simulador/lineas-credito',
                color: 'secondary',
                disponible: true
            }
        ];
    };

    const cargarResumenFinanciero = () => {
        const data = estadisticasData.value;
        if (data?.success) {
            resumenFinanciero.value = data.data;
            financieroError.value = '';
        } else {
            resumenFinanciero.value = null;
            financieroError.value = data?.message || 'Error al cargar resumen financiero';
        }
    };

    const marcarNotificacionLeida = async (_notificacionId: string) => {
        // Implementación futura si se necesitan notificaciones
        console.log('Marcar notificación como leída - no implementado');
    };

    const marcarTodasNotificacionesLeidas = async () => {
        // Implementación futura si se necesitan notificaciones
        console.log('Marcar todas las notificaciones como leídas - no implementado');
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
    const inicializar = () => {
        // Cargar todos los datos desde el servidor (síncrono)
        cargarSolicitudes();
        cargarFlujoAprobacion();
        cargarDashboardStats();
        cargarActividadReciente();
        cargarQuickActions();
        cargarResumenFinanciero();
    };

    const cargarEstados = async () => {
        await cargarFlujoAprobacion();
    };

    return {
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
        notificacionesNoLeidas,
        totalNotificacionesNoLeidas,

        quickActions,
        quickActionsDisponibles,

        resumenFinanciero,
        loadingFinanciero,
        financieroError,
        proximosPagos,
        pagosVencidos,
        pagosPorVencer,

        solicitudesPendientes,
        solicitudesRequierenAccion,

        ultimaSolicitud,
        estadoIndexUltima,

        fmtMoney,
        fmtDate,
        fmtDateTime,

        getEstadoData,
        estadoProgressPercent,
        estadoProgressClass,
        estadoBadgeClass,
        getEstadoInfo,
        getEstadoColor,
        getEstadoIcono,
        requiereAccionUsuario,
        getSiguienteEstado,
        getProgresoEstado,
        getEstadoBadgeColor,

        getDiasEnEstado,
        getDiasParaVencer,

        cargarEstados,
        cargarFlujoAprobacion,
        cargarSolicitudes,
        resetSolicitudes,
        cargarDashboardStats,
        cargarActividadReciente,
        cargarQuickActions,
        cargarResumenFinanciero,
        marcarNotificacionLeida,
        marcarTodasNotificacionesLeidas,

        inicializar,
    };
}
