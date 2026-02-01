import { ref, computed, watch } from 'vue';
import { useSession } from '@/composables/useSession';
import { useApi } from '@/composables/useApi';
import type {
    Perfil,
    PasswordData,
    PerfilUpdateData,
    PerfilPreferences,
    PerfilSecurity,
    PerfilActivity,
    PerfilStats
} from '@/types/perfil';

type ApiSuccessResponse<T> = {
    success: boolean;
    message?: string;
    data?: T;
};

type PerfilApiData = {
    id: string;
    username: string;
    email: string;
    full_name?: string | null;
    phone?: string | null;
    tipo_documento?: string | null;
    numero_documento?: string | null;
    nombres?: string | null;
    apellidos?: string | null;
};

export function usePerfil() {
    const { user, updateUserData } = useSession();
    const { getJson, putJson, postJson } = useApi();

    // Estado del perfil
    const perfil = ref<Perfil>({
        username: '',
        email: '',
        full_name: '',
        phone: '',
        tipo_documento: '',
        numero_documento: '',
        nombres: '',
        apellidos: ''
    });

    const passwordData = ref<PasswordData>({
        password_actual: '',
        nueva_password: '',
        confirmar_password: ''
    });

    const preferences = ref<PerfilPreferences>({
        tema: 'auto',
        idioma: 'es',
        notificaciones_email: true,
        notificaciones_push: true,
        formato_fecha: 'DD/MM/YYYY'
    });

    const security = ref<PerfilSecurity>({
        intentos_fallidos: 0,
        sesiones_activas: []
    });

    const activity = ref<PerfilActivity[]>([]);
    const stats = ref<PerfilStats>({
        solicitudes_creadas: 0,
        solicitudes_aprobadas: 0,
        solicitudes_rechazadas: 0,
        creditos_activos: 0,
        total_prestado: 0
    });

    // Estados de UI
    const loading = ref(false);
    const guardando = ref(false);
    const guardandoPassword = ref(false);
    const error = ref<string | null>(null);
    const success = ref<string | null>(null);
    const showPasswordModal = ref(false);
    const showPreferencesModal = ref(false);
    const showSecurityModal = ref(false);

    // Computed properties
    const perfilCompleto = computed(() => {
        return perfil.value.full_name &&
            perfil.value.phone &&
            perfil.value.tipo_documento &&
            perfil.value.numero_documento;
    });

    const passwordValida = computed(() => {
        return passwordData.value.password_actual &&
            passwordData.value.nueva_password &&
            passwordData.value.confirmar_password &&
            passwordData.value.nueva_password === passwordData.value.confirmar_password &&
            passwordData.value.nueva_password.length >= 8;
    });

    const tieneSesionesActivas = computed(() => {
        return security.value.sesiones_activas.some(sesion => sesion.activa);
    });

    const ultimaSolicitudReciente = computed(() => {
        if (!stats.value.ultima_solicitud) return null;
        return new Date(stats.value.ultima_solicitud).toLocaleDateString('es-CO');
    });

    // Métodos principales
    const cargarPerfil = async () => {
        loading.value = true;
        error.value = null;

        try {
            const response = await getJson<ApiSuccessResponse<PerfilApiData>>('/api/perfil', { auth: true });

            if (response.success && response.data) {
                const data = response.data;
                perfil.value = {
                    username: data.username,
                    email: data.email,
                    full_name: data.full_name || '',
                    phone: data.phone || '',
                    tipo_documento: data.tipo_documento || '',
                    numero_documento: data.numero_documento || '',
                    nombres: data.nombres || '',
                    apellidos: data.apellidos || ''
                };
            }
        } catch (err: any) {
            console.error('Error cargando perfil:', err);
            error.value = err.message || 'Error al cargar el perfil';
        } finally {
            loading.value = false;
        }
    };

    const actualizarPerfil = async () => {
        guardando.value = true;
        error.value = null;
        success.value = null;

        try {
            const updateData: PerfilUpdateData = {
                username: perfil.value.username,
                email: perfil.value.email,
                full_name: perfil.value.full_name,
                phone: perfil.value.phone,
                tipo_documento: perfil.value.tipo_documento,
                numero_documento: perfil.value.numero_documento,
                nombres: perfil.value.nombres,
                apellidos: perfil.value.apellidos
            };

            const response = await putJson<ApiSuccessResponse<PerfilApiData>>('/api/perfil', updateData, { auth: true });

            if (response.success) {
                success.value = 'Perfil actualizado exitosamente';

                // Recargar datos de autenticación vía Inertia
                await updateUserData({});
            }
        } catch (err: any) {
            console.error('Error actualizando perfil:', err);
            error.value = err.message || 'Error al actualizar el perfil';
            throw err;
        } finally {
            guardando.value = false;
        }
    };

    const cambiarPassword = async () => {
        guardandoPassword.value = true;
        error.value = null;
        success.value = null;

        try {
            const response = await putJson<ApiSuccessResponse<any>>('/api/perfil/password', passwordData.value, { auth: true });

            if (response.success) {
                success.value = 'Contraseña actualizada exitosamente';
                showPasswordModal.value = false;

                // Limpiar formulario
                passwordData.value = {
                    password_actual: '',
                    nueva_password: '',
                    confirmar_password: ''
                };
            }
        } catch (err: any) {
            console.error('Error cambiando contraseña:', err);
            error.value = err.message || 'Error al cambiar la contraseña';
            throw err;
        } finally {
            guardandoPassword.value = false;
        }
    };

    const actualizarPreferencias = async () => {
        try {
            const response = await putJson<ApiSuccessResponse<PerfilPreferences>>('/api/perfil/preferences', preferences.value, { auth: true });

            if (response.success) {
                success.value = 'Preferencias actualizadas exitosamente';
                showPreferencesModal.value = false;

                // Aplicar tema inmediatamente
                if (preferences.value.tema !== 'auto') {
                    document.documentElement.setAttribute('data-theme', preferences.value.tema);
                }
            }
        } catch (err: any) {
            console.error('Error actualizando preferencias:', err);
            error.value = err.message || 'Error al actualizar las preferencias';
            throw err;
        }
    };

    const cargarSecurity = async () => {
        try {
            const response = await getJson<ApiSuccessResponse<PerfilSecurity>>('/api/perfil/security', { auth: true });

            if (response.success && response.data) {
                security.value = response.data;
            }
        } catch (err: any) {
            console.error('Error cargando seguridad:', err);
            error.value = err.message || 'Error al cargar información de seguridad';
        }
    };

    const cerrarSesion = async (sessionId: string) => {
        try {
            await postJson('/api/perfil/sessions/close', { session_id: sessionId }, { auth: true });

            // Recargar sesiones activas
            await cargarSecurity();
        } catch (err: any) {
            console.error('Error cerrando sesión:', err);
            error.value = err.message || 'Error al cerrar la sesión';
            throw err;
        }
    };

    const cerrarTodasSesiones = async () => {
        try {
            await postJson('/api/perfil/sessions/close-all', {}, { auth: true });

            // Recargar sesiones activas
            await cargarSecurity();
        } catch (err: any) {
            console.error('Error cerrando todas las sesiones:', err);
            error.value = err.message || 'Error al cerrar todas las sesiones';
            throw err;
        }
    };

    const cargarActivity = async () => {
        try {
            const response = await getJson<ApiSuccessResponse<PerfilActivity[]>>('/api/perfil/activity', { auth: true });

            if (response.success && response.data) {
                activity.value = response.data;
            }
        } catch (err: any) {
            console.error('Error cargando actividad:', err);
            error.value = err.message || 'Error al cargar la actividad';
        }
    };

    const cargarStats = async () => {
        try {
            const response = await getJson<ApiSuccessResponse<PerfilStats>>('/api/perfil/stats', { auth: true });

            if (response.success && response.data) {
                stats.value = response.data;
            }
        } catch (err: any) {
            console.error('Error cargando estadísticas:', err);
            error.value = err.message || 'Error al cargar las estadísticas';
        }
    };

    // Métodos de modales
    const abrirModalPassword = () => {
        passwordData.value = {
            password_actual: '',
            nueva_password: '',
            confirmar_password: ''
        };
        showPasswordModal.value = true;
    };

    const cerrarModalPassword = () => {
        showPasswordModal.value = false;
        passwordData.value = {
            password_actual: '',
            nueva_password: '',
            confirmar_password: ''
        };
    };

    const abrirModalPreferences = () => {
        showPreferencesModal.value = true;
    };

    const cerrarModalPreferences = () => {
        showPreferencesModal.value = false;
    };

    const abrirModalSecurity = () => {
        showSecurityModal.value = true;
    };

    const cerrarModalSecurity = () => {
        showSecurityModal.value = false;
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

    const formatoFechaCorta = (fecha: string): string => {
        return new Date(fecha).toLocaleDateString('es-CO');
    };

    const getDispositivoIcono = (dispositivo: string): string => {
        if (dispositivo.toLowerCase().includes('mobile')) return '📱';
        if (dispositivo.toLowerCase().includes('tablet')) return '📱';
        if (dispositivo.toLowerCase().includes('desktop')) return '💻';
        return '🖥️';
    };

    const getAccionIcono = (accion: string): string => {
        const iconos: Record<string, string> = {
            'login': '🔑',
            'logout': '🚪',
            'perfil_actualizado': '✏️',
            'password_cambiada': '🔒',
            'solicitud_creada': '📄',
            'documento_subido': '📎',
            'firma_digital': '✍️'
        };
        return iconos[accion] || '📝';
    };

    // Validaciones
    const validarEmail = (email: string): boolean => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    };

    const validarPassword = (password: string): { valida: boolean; errores: string[] } => {
        const errores: string[] = [];

        if (password.length < 8) {
            errores.push('La contraseña debe tener al menos 8 caracteres');
        }

        if (!/[A-Z]/.test(password)) {
            errores.push('La contraseña debe tener al menos una mayúscula');
        }

        if (!/[a-z]/.test(password)) {
            errores.push('La contraseña debe tener al menos una minúscula');
        }

        if (!/[0-9]/.test(password)) {
            errores.push('La contraseña debe tener al menos un número');
        }

        if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            errores.push('La contraseña debe tener al menos un carácter especial');
        }

        return {
            valida: errores.length === 0,
            errores
        };
    };

    // Watchers
    watch(() => user.value, (newUser) => {
        if (newUser) {
            perfil.value = {
                username: newUser.username || '',
                email: newUser.email || '',
                full_name: newUser.full_name || '',
                phone: newUser.phone || '',
                tipo_documento: newUser.tipo_documento || '',
                numero_documento: newUser.numero_documento || '',
                nombres: newUser.nombres || '',
                apellidos: newUser.apellidos || ''
            };
        }
    }, { immediate: true });

    // Inicialización
    const inicializar = async () => {
        await Promise.all([
            cargarPerfil(),
            cargarSecurity(),
            cargarActivity(),
            cargarStats()
        ]);
    };

    return {
        // Estado
        perfil,
        passwordData,
        preferences,
        security,
        activity,
        stats,
        loading,
        guardando,
        guardandoPassword,
        error,
        success,
        showPasswordModal,
        showPreferencesModal,
        showSecurityModal,

        // Computed
        perfilCompleto,
        passwordValida,
        tieneSesionesActivas,
        ultimaSolicitudReciente,

        // Métodos principales
        cargarPerfil,
        actualizarPerfil,
        cambiarPassword,
        actualizarPreferencias,
        cargarSecurity,
        cargarActivity,
        cargarStats,

        // Métodos de seguridad
        cerrarSesion,
        cerrarTodasSesiones,

        // Modales
        abrirModalPassword,
        cerrarModalPassword,
        abrirModalPreferences,
        cerrarModalPreferences,
        abrirModalSecurity,
        cerrarModalSecurity,

        // Utilidades
        formatoFecha,
        formatoFechaCorta,
        getDispositivoIcono,
        getAccionIcono,

        // Validaciones
        validarEmail,
        validarPassword,

        // Inicialización
        inicializar,
    };
}
