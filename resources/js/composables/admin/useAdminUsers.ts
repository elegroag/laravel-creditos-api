import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useApi } from '@/composables/useApi';
import { useSession } from '@/composables/useSession';
import type { Usuario, Paginacion } from '@/types/admin';

export function useAdminUsers() {
    const { getJson, putJson } = useApi();
    const { isAuthenticated } = useSession();

    // Estado
    const usuarios = ref<Usuario[]>([]);
    const loading = ref(true);
    const error = ref<string | null>(null);
    const totalUsuarios = ref(0);
    const conteoRoles = ref<Record<string, number>>({});
    const conteoEstados = ref<Record<string, number>>({});

    // Filtros
    const filtros = ref({
        rol: '',
        busqueda: '',
        estado: '',
    });

    // Paginación
    const paginacion = ref<Paginacion>({
        limit: 10,
        offset: 0,
    });

    // Debounce para búsqueda
    const debounceSearch = ref<NodeJS.Timeout | null>(null);

    // Computed properties
    const paginaActual = computed(() => Math.floor(paginacion.value.offset / paginacion.value.limit) + 1);
    const totalPaginas = computed(() => Math.ceil(totalUsuarios.value / paginacion.value.limit));

    const paginasVisibles = computed(() => {
        const paginas: number[] = [];
        const maxPaginasVisibles = 5;
        const mitad = Math.floor(maxPaginasVisibles / 2);

        let inicio = Math.max(1, paginaActual.value - mitad);
        let fin = Math.min(totalPaginas.value, inicio + maxPaginasVisibles - 1);

        if (fin - inicio + 1 < maxPaginasVisibles) {
            inicio = Math.max(1, fin - maxPaginasVisibles + 1);
        }

        for (let i = inicio; i <= fin; i++) {
            paginas.push(i);
        }

        return paginas;
    });

    const tieneSiguiente = computed(() => paginaActual.value < totalPaginas.value);
    const tieneAnterior = computed(() => paginaActual.value > 1);

    // Métodos
    const cargarUsuarios = async () => {
        if (!isAuthenticated.value) return;

        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams({
                limit: paginacion.value.limit.toString(),
                offset: paginacion.value.offset.toString(),
            });

            if (filtros.value.rol) {
                params.append('rol', filtros.value.rol);
            }

            if (filtros.value.estado) {
                params.append('estado', filtros.value.estado);
            }

            if (filtros.value.busqueda) {
                params.append('busqueda', filtros.value.busqueda);
            }

            const response = await getJson<{
                data: Usuario[];
                total: number;
                conteo_roles: Record<string, number>;
                conteo_estados: Record<string, number>;
            }>(`/api/admin/usuarios?${params.toString()}`, { auth: true });

            usuarios.value = response.data;
            totalUsuarios.value = response.total;
            conteoRoles.value = response.conteo_roles;
            conteoEstados.value = response.conteo_estados;
        } catch (err: any) {
            console.error('Error cargando usuarios:', err);
            error.value = err.message || 'Error al cargar los usuarios';
        } finally {
            loading.value = false;
        }
    };

    const cambiarPagina = (pagina: number) => {
        const nuevoOffset = (pagina - 1) * paginacion.value.limit;
        paginacion.value.offset = nuevoOffset;
        cargarUsuarios();
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

    const aplicarFiltros = () => {
        paginacion.value.offset = 0; // Resetear a primera página
        cargarUsuarios();
    };

    const limpiarFiltros = () => {
        filtros.value = {
            rol: '',
            busqueda: '',
            estado: '',
        };
        aplicarFiltros();
    };

    const cambiarEstadoUsuario = async (usuarioId: string, nuevoEstado: string) => {
        try {
            await putJson(`/api/admin/usuarios/${usuarioId}/estado`, {
                estado: nuevoEstado,
            }, { auth: true });

            // Actualizar el usuario en la lista local
            const usuarioIndex = usuarios.value.findIndex(u => u.id === usuarioId);
            if (usuarioIndex !== -1) {
                usuarios.value[usuarioIndex].estado = nuevoEstado;
            }

            // Recargar conteos
            await cargarUsuarios();
        } catch (err: any) {
            console.error('Error cambiando estado del usuario:', err);
            throw err;
        }
    };

    const eliminarUsuario = async (usuarioId: string) => {
        try {
            await router.delete(`/api/admin/usuarios/${usuarioId}`, {
                preserveState: false,
                preserveScroll: true,
            });

            // Recargar la lista
            await cargarUsuarios();
        } catch (err: any) {
            console.error('Error eliminando usuario:', err);
            throw err;
        }
    };

    // Watch para búsqueda con debounce
    const debouncedSearch = () => {
        if (debounceSearch.value) {
            clearTimeout(debounceSearch.value);
        }

        debounceSearch.value = setTimeout(() => {
            aplicarFiltros();
        }, 500);
    };

    // Watch para cambios en filtros
    const actualizarBusqueda = () => {
        debouncedSearch();
    };

    // Inicialización
    const inicializar = async () => {
        await cargarUsuarios();
    };

    return {
        // Estado
        usuarios,
        loading,
        error,
        totalUsuarios,
        conteoRoles,
        conteoEstados,
        filtros,
        paginacion,

        // Computed
        paginaActual,
        totalPaginas,
        paginasVisibles,
        tieneSiguiente,
        tieneAnterior,

        // Métodos
        cargarUsuarios,
        cambiarPagina,
        siguientePagina,
        anteriorPagina,
        aplicarFiltros,
        limpiarFiltros,
        cambiarEstadoUsuario,
        eliminarUsuario,
        actualizarBusqueda,
        inicializar,
    };
}
