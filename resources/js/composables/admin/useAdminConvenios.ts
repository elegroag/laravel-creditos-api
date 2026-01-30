import { ref, computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useApi } from '@/composables/useApi';
import { useSession } from '@/composables/useSession';
import type { 
    EmpresaConvenio, 
    PaginationInfo, 
    ConveniosResponse,
    CreateConvenioForm,
    UpdateConvenioForm,
    ConvenioValidationRequest,
    ConvenioValidationResponse,
    ConvenioStats
} from '@/types/convenios';

export function useAdminConvenios() {
    const { getJson, postJson, putJson, deleteJson } = useApi();
    const { isAuthenticated } = useSession();

    // Estado reactivo
    const empresas = ref<EmpresaConvenio[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const totalEmpresas = ref(0);
    const conteoEstados = ref<Record<string, number>>({});

    // Filtros
    const filtros = reactive({
        estado: '',
        nit: '',
        busqueda: '',
    });

    // Paginación
    const paginacion = reactive({
        page: 1,
        limit: 20,
        offset: 0,
    });

    // Estado para modales
    const showCreateModal = ref(false);
    const showEditModal = ref(false);
    const showValidationModal = ref(false);
    const empresaSeleccionada = ref<EmpresaConvenio | null>(null);
    const loadingModal = ref(false);

    // Formularios
    const createForm = ref<CreateConvenioForm>({
        nit: '',
        razon_social: '',
        representante_documento: '',
        representante_nombre: '',
        telefono: '',
        correo: '',
        fecha_convenio: '',
        fecha_vencimiento: '',
        estado: 'activo',
    });

    const editForm = ref<UpdateConvenioForm>({});

    // Validación
    const validationForm = ref<ConvenioValidationRequest>({
        nit: '',
        cedula_trabajador: '',
    });

    const validationResponse = ref<ConvenioValidationResponse | null>(null);
    const loadingValidation = ref(false);

    // Estadísticas
    const stats = ref<ConvenioStats | null>(null);
    const loadingStats = ref(false);

    // Computed properties
    const paginaActual = computed(() => paginacion.page);
    const totalPaginas = computed(() => Math.ceil(totalEmpresas.value / paginacion.limit));

    const paginasVisibles = computed(() => {
        const total = totalPaginas.value;
        const actual = paginaActual.value;
        const delta = 2;
        const range = [];
        const rangeWithDots = [];

        for (let i = Math.max(2, actual - delta); i <= Math.min(total - 1, actual + delta); i++) {
            range.push(i);
        }

        if (actual - delta > 2) {
            rangeWithDots.push(1, '...');
        } else {
            rangeWithDots.push(1);
        }

        rangeWithDots.push(...range);

        if (actual + delta < total - 1) {
            rangeWithDots.push('...', total);
        } else if (actual + delta === total - 1) {
            rangeWithDots.push(total);
        }

        return rangeWithDots;
    });

    const tieneSiguiente = computed(() => paginacion.page < totalPaginas.value);
    const tieneAnterior = computed(() => paginacion.page > 1);

    const empresasFiltradas = computed(() => {
        return empresas.value.filter(empresa => {
            const coincideEstado = !filtros.estado || empresa.estado === filtros.estado;
            const coincideNit = !filtros.nit || empresa.nit.includes(filtros.nit);
            const coincideBusqueda = !filtros.busqueda || 
                empresa.razon_social.toLowerCase().includes(filtros.busqueda.toLowerCase()) ||
                empresa.nit.includes(filtros.busqueda) ||
                empresa.representante_nombre.toLowerCase().includes(filtros.busqueda.toLowerCase());

            return coincideEstado && coincideNit && coincideBusqueda;
        });
    });

    // Métodos principales
    const cargarEmpresas = async () => {
        if (!isAuthenticated.value) return;

        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams({
                page: paginacion.page.toString(),
                limit: paginacion.limit.toString(),
                offset: paginacion.offset.toString(),
            });

            if (filtros.estado) params.append('estado', filtros.estado);
            if (filtros.nit) params.append('nit', filtros.nit);
            if (filtros.busqueda) params.append('busqueda', filtros.busqueda);

            const response = await getJson<ConveniosResponse>(`/api/admin/convenios?${params.toString()}`, { auth: true });

            empresas.value = response.empresas;
            totalEmpresas.value = response.pagination.total;
            conteoEstados.value = response.conteo_estados;
        } catch (err: any) {
            console.error('Error cargando empresas:', err);
            error.value = err.message || 'Error al cargar las empresas';
        } finally {
            loading.value = false;
        }
    };

    const cargarStats = async () => {
        loadingStats.value = true;
        try {
            const response = await getJson<ConvenioStats>('/api/admin/convenios/stats', { auth: true });
            stats.value = response;
        } catch (err: any) {
            console.error('Error cargando estadísticas:', err);
        } finally {
            loadingStats.value = false;
        }
    };

    // Métodos de paginación
    const cambiarPagina = (pagina: number) => {
        paginacion.page = pagina;
        paginacion.offset = (pagina - 1) * paginacion.limit;
        cargarEmpresas();
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
    const aplicarFiltros = () => {
        paginacion.page = 1;
        paginacion.offset = 0;
        cargarEmpresas();
    };

    const limpiarFiltros = () => {
        filtros.estado = '';
        filtros.nit = '';
        filtros.busqueda = '';
        aplicarFiltros();
    };

    // Métodos CRUD
    const crearConvenio = async () => {
        loadingModal.value = true;
        error.value = null;

        try {
            await postJson('/api/admin/convenios', createForm.value, { auth: true });
            
            showCreateModal.value = false;
            resetCreateForm();
            await cargarEmpresas();
            await cargarStats();
        } catch (err: any) {
            console.error('Error creando convenio:', err);
            error.value = err.message || 'Error al crear el convenio';
            throw err;
        } finally {
            loadingModal.value = false;
        }
    };

    const actualizarConvenio = async () => {
        if (!empresaSeleccionada.value) return;

        loadingModal.value = true;
        error.value = null;

        try {
            await putJson(`/api/admin/convenios/${empresaSeleccionada.value.id}`, editForm.value, { auth: true });
            
            showEditModal.value = false;
            empresaSeleccionada.value = null;
            editForm.value = {};
            await cargarEmpresas();
        } catch (err: any) {
            console.error('Error actualizando convenio:', err);
            error.value = err.message || 'Error al actualizar el convenio';
            throw err;
        } finally {
            loadingModal.value = false;
        }
    };

    const eliminarConvenio = async (empresaId: string) => {
        try {
            await deleteJson(`/api/admin/convenios/${empresaId}`, { auth: true });
            
            await cargarEmpresas();
            await cargarStats();
        } catch (err: any) {
            console.error('Error eliminando convenio:', err);
            error.value = err.message || 'Error al eliminar el convenio';
            throw err;
        }
    };

    // Métodos de validación
    const validarConvenio = async () => {
        loadingValidation.value = true;
        error.value = null;

        try {
            const response = await postJson<ConvenioValidationResponse>('/api/admin/convenios/validar', validationForm.value, { auth: true });
            validationResponse.value = response;
            showValidationModal.value = true;
        } catch (err: any) {
            console.error('Error validando convenio:', err);
            error.value = err.message || 'Error al validar el convenio';
            throw err;
        } finally {
            loadingValidation.value = false;
        }
    };

    // Métodos de modales
    const abrirModalCreacion = () => {
        resetCreateForm();
        showCreateModal.value = true;
    };

    const cerrarModalCreacion = () => {
        showCreateModal.value = false;
        resetCreateForm();
    };

    const abrirModalEdicion = (empresa: EmpresaConvenio) => {
        empresaSeleccionada.value = empresa;
        editForm.value = { ...empresa };
        showEditModal.value = true;
    };

    const cerrarModalEdicion = () => {
        showEditModal.value = false;
        empresaSeleccionada.value = null;
        editForm.value = {};
    };

    const cerrarModalValidacion = () => {
        showValidationModal.value = false;
        validationResponse.value = null;
        validationForm.value = { nit: '', cedula_trabajador: '' };
    };

    // Utilidades
    const resetCreateForm = () => {
        createForm.value = {
            nit: '',
            razon_social: '',
            representante_documento: '',
            representante_nombre: '',
            telefono: '',
            correo: '',
            fecha_convenio: '',
            fecha_vencimiento: '',
            estado: 'activo',
        };
    };

    const formatoFecha = (fecha: string): string => {
        return new Date(fecha).toLocaleDateString('es-CO');
    };

    const getEstadoColor = (estado: string): string => {
        const colores: Record<string, string> = {
            'activo': 'green',
            'inactivo': 'red',
            'vencido': 'orange',
            'por_vencer': 'yellow'
        };
        return colores[estado] || 'gray';
    };

    const getEstadoLabel = (estado: string): string => {
        const labels: Record<string, string> = {
            'activo': 'Activo',
            'inactivo': 'Inactivo',
            'vencido': 'Vencido',
            'por_vencer': 'Por Vencer'
        };
        return labels[estado] || estado;
    };

    const estaPorVencer = (fechaVencimiento: string): boolean => {
        const vencimiento = new Date(fechaVencimiento);
        const hoy = new Date();
        const diasParaVencer = Math.ceil((vencimiento.getTime() - hoy.getTime()) / (1000 * 60 * 60 * 24));
        return diasParaVencer <= 30 && diasParaVencer > 0;
    };

    const estaVencido = (fechaVencimiento: string): boolean => {
        return new Date(fechaVencimiento) < new Date();
    };

    // Inicialización
    const inicializar = async () => {
        await Promise.all([
            cargarEmpresas(),
            cargarStats()
        ]);
    };

    return {
        // Estado
        empresas,
        loading,
        error,
        totalEmpresas,
        conteoEstados,
        filtros,
        paginacion,
        stats,
        loadingStats,

        // Modales
        showCreateModal,
        showEditModal,
        showValidationModal,
        empresaSeleccionada,
        loadingModal,
        createForm,
        editForm,
        validationForm,
        validationResponse,
        loadingValidation,

        // Computed
        paginaActual,
        totalPaginas,
        paginasVisibles,
        tieneSiguiente,
        tieneAnterior,
        empresasFiltradas,

        // Métodos principales
        cargarEmpresas,
        cargarStats,

        // Paginación
        cambiarPagina,
        siguientePagina,
        anteriorPagina,

        // Filtros
        aplicarFiltros,
        limpiarFiltros,

        // CRUD
        crearConvenio,
        actualizarConvenio,
        eliminarConvenio,

        // Validación
        validarConvenio,

        // Modales
        abrirModalCreacion,
        cerrarModalCreacion,
        abrirModalEdicion,
        cerrarModalEdicion,
        cerrarModalValidacion,

        // Utilidades
        formatoFecha,
        getEstadoColor,
        getEstadoLabel,
        estaPorVencer,
        estaVencido,

        // Inicialización
        inicializar,
    };
}
