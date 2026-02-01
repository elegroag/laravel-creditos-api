import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSession } from '@/composables/useSession';

export function useLogin() {
    const { isAuthenticated } = useSession();

    const username = ref('');
    const password = ref('');
    const loading = ref(false);
    const errorMsg = ref('');

    const login = async () => {
        loading.value = true;
        errorMsg.value = '';

        try {
            // Usar el login web de Laravel con Inertia
            await router.post('login', {
                username: username.value,
                password: password.value
            }, {
                onError: (errors) => {
                    // Manejar errores de validación de Laravel
                    if (errors.username) {
                        errorMsg.value = errors.username;
                    } else if (errors.password) {
                        errorMsg.value = errors.password;
                    } else {
                        errorMsg.value = 'Credenciales inválidas';
                    }
                },
                onSuccess: () => { }
            });

            return true;
        } catch (e: any) {
            // Inertia maneja las redirecciones automáticamente, pero si hay un error no manejado
            errorMsg.value = 'No fue posible iniciar sesión';
            return false;
        } finally {
            loading.value = false;
        }
    };

    const checkAuthAndRedirect = async () => {
        if (isAuthenticated.value) {
            router.visit('/web/inicio');
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
