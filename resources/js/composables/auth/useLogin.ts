import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useApi } from '@/composables/useApi';
import { useSession } from '@/composables/useSession';
import type { LoginData } from '@/types/auth';
import type { Trabajador } from '@/types/auth';

export function useLogin() {
    const { postJson } = useApi();
    const { setSession } = useSession();

    const username = ref('');
    const password = ref('');
    const loading = ref(false);
    const errorMsg = ref('');

    const login = async () => {
        loading.value = true;
        errorMsg.value = '';

        try {
            // En Inertia, usamos el endpoint de Laravel directamente
            const response = await postJson<any>('/login', {
                username: username.value,
                password: password.value
            });

            // En Laravel con Inertia, la redirección se maneja automáticamente
            // Solo necesitamos actualizar el estado de la sesión si es necesario
            if (response.user) {
                const accessToken = response.access_token || '';
                const tokenType = response.token_type || 'bearer';
                const user = response.user;
                const trabajadorData = user?.trabajador;

                if (!accessToken) {
                    throw new Error('Respuesta inválida de autenticación');
                }

                // Validar y extraer datos del trabajador
                let trabajador: Trabajador | undefined;
                if (trabajadorData && typeof trabajadorData === 'object') {
                    trabajador = {
                        cargo: typeof trabajadorData.cargo === 'string' ? trabajadorData.cargo : '',
                        cedula: typeof trabajadorData.cedula === 'string' ? trabajadorData.cedula : '',
                        ciudad_codigo: typeof trabajadorData.ciudad_codigo === 'string' ? trabajadorData.ciudad_codigo : '',
                        ciudad_nacimiento: typeof trabajadorData.ciudad_nacimiento === 'string' ? trabajadorData.ciudad_nacimiento : '',
                        direccion: typeof trabajadorData.direccion === 'string' ? trabajadorData.direccion : '',
                        email: typeof trabajadorData.email === 'string' ? trabajadorData.email : '',
                        empresa: trabajadorData.empresa && typeof trabajadorData.empresa === 'object' ? {
                            ciudad_codigo: typeof trabajadorData.empresa.ciudad_codigo === 'string' ? trabajadorData.empresa.ciudad_codigo : '',
                            direccion: typeof trabajadorData.empresa.direccion === 'string' ? trabajadorData.empresa.direccion : '',
                            nit: typeof trabajadorData.empresa.nit === 'string' ? trabajadorData.empresa.nit : '',
                            razon_social: typeof trabajadorData.empresa.razon_social === 'string' ? trabajadorData.empresa.razon_social : '',
                            telefono: typeof trabajadorData.empresa.telefono === 'string' ? trabajadorData.empresa.telefono : ''
                        } : {
                            ciudad_codigo: '',
                            direccion: '',
                            nit: '',
                            razon_social: '',
                            telefono: ''
                        },
                        estado: typeof trabajadorData.estado === 'string' ? trabajadorData.estado : '',
                        estado_civil: typeof trabajadorData.estado_civil === 'string' ? trabajadorData.estado_civil : '',
                        fecha_afiliacion: typeof trabajadorData.fecha_afiliacion === 'string' ? trabajadorData.fecha_afiliacion : '',
                        fecha_nacimiento: typeof trabajadorData.fecha_nacimiento === 'string' ? trabajadorData.fecha_nacimiento : '',
                        fecha_salario: typeof trabajadorData.fecha_salario === 'string' ? trabajadorData.fecha_salario : '',
                        nivel_educativo: typeof trabajadorData.nivel_educativo === 'string' ? trabajadorData.nivel_educativo : '',
                        primer_apellido: typeof trabajadorData.primer_apellido === 'string' ? trabajadorData.primer_apellido : '',
                        primer_nombre: typeof trabajadorData.primer_nombre === 'string' ? trabajadorData.primer_nombre : '',
                        salario: typeof trabajadorData.salario === 'number' ? trabajadorData.salario : 0,
                        segundo_apellido: typeof trabajadorData.segundo_apellido === 'string' ? trabajadorData.segundo_apellido : '',
                        segundo_nombre: typeof trabajadorData.segundo_nombre === 'string' ? trabajadorData.segundo_nombre : '',
                        sexo: typeof trabajadorData.sexo === 'string' ? trabajadorData.sexo : '',
                        telefono: typeof trabajadorData.telefono === 'string' ? trabajadorData.telefono : '',
                        tipo_documento: typeof trabajadorData.tipo_documento === 'string' ? trabajadorData.tipo_documento : '',
                        codigo_categoria: typeof trabajadorData.codigo_categoria === 'string' ? trabajadorData.codigo_categoria : ''
                    };
                }

                setSession({
                    accessToken,
                    tokenType,
                    user: {
                        id: user?.id || 0,
                        username: typeof user?.username === 'string' ? user.username : username.value,
                        roles: Array.isArray(user?.roles) ? user.roles : [],
                        permissions: Array.isArray(user?.permissions) ? user.permissions : [],
                        email: typeof user?.email === 'string' ? user.email : '',
                        tipo_documento: typeof user?.tipo_documento === 'string' ? user.tipo_documento : '',
                        numero_documento: typeof user?.numero_documento === 'string' ? user.numero_documento : '',
                        nombres: typeof user?.nombres === 'string' ? user.nombres : '',
                        apellidos: typeof user?.apellidos === 'string' ? user.apellidos : '',
                        full_name: typeof user?.full_name === 'string' ? user.full_name : `${user?.nombres || ''} ${user?.apellidos || ''}`.trim(),
                        trabajador
                    }
                });
            }

            return true;
        } catch (e: any) {
            const status = Number(e?.statusCode || e?.response?.status || 0);
            const code = e?.data?.code;

            if (status === 404 && code === 'USER_NOT_FOUND') {
                // Redirigir a registro si el usuario no existe
                router.get('/register', {
                    username: username.value.trim(),
                    redirect: window.location.pathname
                });
                return false;
            }

            errorMsg.value = e?.data?.error || e?.message || 'No fue posible iniciar sesión';
            return false;
        } finally {
            loading.value = false;
        }
    };

    const checkAuthAndRedirect = async () => {
        const { isAuthenticated } = useSession();
        
        if (isAuthenticated.value) {
            router.visit('/dashboard');
        }
    };

    return {
        username,
        password,
        loading,
        errorMsg,
        login,
        checkAuthAndRedirect
    };
}
