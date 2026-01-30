import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useApi } from '@/composables/useApi';
import { useSession } from '@/composables/useSession';
import type { 
    FiltrosSolicitudes, 
    SolicitudAdmin, 
    EstadosCount, 
    OpcionesFiltro,
    CambioEstadoRequest,
    SolicitudDetalle,
    AccionesSolicitud
} from '@/types/admin-solicitudes';

export const useAdminSolicitudes = () => {
    const { getJson, postJson, putJson, deleteJson } = useApi();
    const { isAuthenticated, user } = useSession();

    // Estado reactivo
    const solicitudes = ref<SolicitudAdmin[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const totalItems = ref(0);

    // Filtros activos
    const filtrosActivos = ref<FiltrosSolicitudes>({
        skip: 0,
        limit: 20
    });

    // Opciones para filtros
    const opcionesFiltro = ref<OpcionesFiltro>({
        estados: [],
        usuarios: []
    });

    // Conteo por estados
    const estadosCount = ref<EstadosCount>({});

    // Estado para el modal de cambio de estado
    const showEstadoModal = ref(false);
    const solicitudSeleccionada = ref<SolicitudAdmin | null>(null);
    const nuevoEstado = ref('');
    const estadoDescripcion = ref('');
    const loadingEstado = ref(false);

    // Estado para detalles
    const solicitudDetalle = ref<SolicitudDetalle | null>(null);
    const loadingDetalle = ref(false);
    const showDetalleModal = ref(false);

    // Computed properties
    const paginaActual = computed(() => Math.floor(filtrosActivos.value.skip! / filtrosActivos.value.limit!) + 1);
    const totalPaginas = computed(() => Math.ceil(totalItems.value / filtrosActivos.value.limit!));

    const tieneSiguiente = computed(() => paginaActual.value < totalPaginas.value);
    const tieneAnterior = computed(() => paginaActual.value > 1);

    const solicitudesFiltradas = computed(() => {
        return solicitudes.value;
    });

    // Métodos principales
    const cargarSolicitudes = async () => {
        if (!isAuthenticated.value) return;

        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();
            
            // Agregar filtros a los parámetros
            Object.entries(filtrosActivos.value).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    if (Array.isArray(value)) {
                        value.forEach(v => params.append(`${key}[]`, String(v)));
                    } else {
                        params.append(key, String(value));
                    }
                }
            });

            const response = await getJson<{
                data: SolicitudAdmin[];
                total: number;
                conteo_estados: EstadosCount;
            }>(`/api/admin/solicitudes?${params.toString()}`, { auth: true });

            solicitudes.value = response.data;
            totalItems.value = response.total;
            estadosCount.value = response.conteo_estados;
        } catch (err: any) {
            console.error('Error cargando solicitudes:', err);
            error.value = err.message || 'Error al cargar las solicitudes';
        } finally {
            loading.value = false;
        }
    };

    const cargarOpcionesFiltro = async () => {
        try {
            const response = await getJson<OpcionesFiltro>('/api/admin/solicitudes/opciones-filtro', { auth: true });
            opcionesFiltro.value = response;
        } catch (err: any) {
            console.error('Error cargando opciones de filtro:', err);
        }
    };

    const cargarDetalleSolicitud = async (solicitudId: string) => {
        loadingDetalle.value = true;
        error.value = null;

        try {
            const response = await getJson<SolicitudDetalle>(`/api/admin/solicitudes/${solicitudId}`, { auth: true });
            solicitudDetalle.value = response;
            showDetalleModal.value = true;
        } catch (err: any) {
            console.error('Error cargando detalle de solicitud:', err);
            error.value = err.message || 'Error al cargar el detalle de la solicitud';
        } finally {
            loadingDetalle.value = false;
        }
    };

    // Métodos de paginación
    const cambiarPagina = (pagina: number) => {
        const nuevoSkip = (pagina - 1) * filtrosActivos.value.limit!;
        filtrosActivos.value.skip = nuevoSkip;
        cargarSolicitudes();
    };

    const siguientePagina = () => {
        if (tieneSiguiente.value) {
            cambiarPagina(paginaActual.value + 1);
        }
    };

    const anteriorPagina = () => {
        if (tieneAnterior.value) {
            cambiarPagina(paginaActual.value - 1);
        }
    };

    // Métodos de filtros
    const aplicarFiltros = (nuevosFiltros: Partial<FiltrosSolicitudes>) => {
        filtrosActivos.value = { ...filtrosActivos.value, ...nuevosFiltros, skip: 0 };
        cargarSolicitudes();
    };

    const limpiarFiltros = () => {
        filtrosActivos.value = {
            skip: 0,
            limit: 20
        };
        cargarSolicitudes();
    };

    const actualizarFiltro = (campo: keyof FiltrosSolicitudes, valor: any) => {
        filtrosActivos.value[campo] = valor;
    };

    // Métodos de cambio de estado
    const abrirModalEstado = (solicitud: SolicitudAdmin) => {
        solicitudSeleccionada.value = solicitud;
        nuevoEstado.value = solicitud.estado;
        estadoDescripcion.value = '';
        showEstadoModal.value = true;
    };

    const cerrarModalEstado = () => {
        showEstadoModal.value = false;
        solicitudSeleccionada.value = null;
        nuevoEstado.value = '';
        estadoDescripcion.value = '';
        loadingEstado.value = false;
    };

    const cambiarEstado = async () => {
        if (!solicitudSeleccionada.value) return;

        loadingEstado.value = true;
        error.value = null;

        try {
            const request: CambioEstadoRequest = {
                estado: nuevoEstado.value,
                descripcion: estadoDescripcion.value,
                notificar_usuario: true
            };

            await putJson(`/api/admin/solicitudes/${solicitudSeleccionada.value.id}/estado`, request, { auth: true });

            // Actualizar la solicitud en la lista local
            const index = solicitudes.value.findIndex(s => s.id === solicitudSeleccionada.value!.id);
            if (index !== -1) {
                solicitudes.value[index].estado = nuevoEstado.value;
            }

            // Recargar conteos
            await cargarSolicitudes();
            cerrarModalEstado();
        } catch (err: any) {
            console.error('Error cambiando estado:', err);
            error.value = err.message || 'Error al cambiar el estado';
        } finally {
            loadingEstado.value = false;
        }
    };

    // Métodos de eliminación
    const eliminarSolicitud = async (solicitudId: string) => {
        try {
            await deleteJson(`/api/admin/solicitudes/${solicitudId}`, { auth: true });
            
            // Recargar la lista
            await cargarSolicitudes();
        } catch (err: any) {
            console.error('Error eliminando solicitud:', err);
            error.value = err.message || 'Error al eliminar la solicitud';
            throw err;
        }
    };

    // Métodos de exportación
    const exportarExcel = async () => {
        try {
            const params = new URLSearchParams();
            Object.entries(filtrosActivos.value).forEach(([key, value]) => {
                if (value !== undefined && value !== null && value !== '') {
                    if (Array.isArray(value)) {
                        value.forEach(v => params.append(`${key}[]`, String(v)));
                    } else {
                        params.append(key, String(value));
                    }
                }
            });

            const response = await getJson(`/api/admin/solicitudes/exportar?${params.toString()}`, { auth: true });
            
            // Descargar archivo
            if (response.url) {
                window.open(response.url, '_blank');
            }
        } catch (err: any) {
            console.error('Error exportando solicitudes:', err);
            error.value = err.message || 'Error al exportar las solicitudes';
        }
    };

    // Métodos de utilidad
    const formatoMoneda = (valor: number): string => {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(valor);
    };

    const formatoFecha = (fecha: string): string => {
        return new Date(fecha).toLocaleDateString('es-CO');
    };

    const getEstadoColor = (estado: string): string => {
        const colores: Record<string, string> = {
            'pendiente': 'yellow',
            'en_revision': 'blue',
            'aprobada': 'green',
            'rechazada': 'red',
            'completada': 'purple',
            'cancelada': 'gray'
        };
        return colores[estado] || 'gray';
    };

    const getAccionesPermitidas = (solicitud: SolicitudAdmin): AccionesSolicitud => {
        // Lógica para determinar acciones permitidas según rol y estado
        const esAdmin = user.value?.roles.includes('admin');
        const esPropietario = user.value?.id === solicitud.owner.id;
        
        return {
            puede_cambiar_estado: esAdmin,
            puede_ver_detalle: esAdmin || esPropietario,
            puede_editar: esAdmin && solicitud.estado === 'pendiente',
            puede_eliminar: esAdmin && ['pendiente', 'rechazada'].includes(solicitud.estado),
            puede_descargar: true,
            puede_firmar: esPropietario && ['aprobada', 'en_revision'].includes(solicitud.estado),
            estados_permitidos: esAdmin ? ['pendiente', 'en_revision', 'aprobada', 'rechazada', 'completada', 'cancelada'] : []
        };
    };

    // Inicialización
    const inicializar = async () => {
        await Promise.all([
            cargarSolicitudes(),
            cargarOpcionesFiltro()
        ]);
    };

    return {
        // Estado
        solicitudes,
        loading,
        error,
        totalItems,
        filtrosActivos,
        opcionesFiltro,
        estadosCount,
        solicitudDetalle,
        loadingDetalle,
        showDetalleModal,
        showEstadoModal,
        solicitudSeleccionada,
        nuevoEstado,
        estadoDescripcion,
        loadingEstado,

        // Computed
        paginaActual,
        totalPaginas,
        tieneSiguiente,
        tieneAnterior,
        solicitudesFiltradas,

        // Métodos principales
        cargarSolicitudes,
        cargarOpcionesFiltro,
        cargarDetalleSolicitud,

        // Paginación
        cambiarPagina,
        siguientePagina,
        anteriorPagina,

        // Filtros
        aplicarFiltros,
        limpiarFiltros,
        actualizarFiltro,

        // Cambio de estado
        abrirModalEstado,
        cerrarModalEstado,
        cambiarEstado,

        // Eliminación
        eliminarSolicitud,

        // Exportación
        exportarExcel,

        // Utilidades
        formatoMoneda,
        formatoFecha,
        getEstadoColor,
        getAccionesPermitidas,

        // Inicialización
        inicializar,
    };
};
