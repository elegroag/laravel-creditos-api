import { ref } from 'vue';
import { router } from '@inertiajs/core';
import { useApi } from '@/composables/useApi';
import { useSession } from '@/composables/useSession';
import type { AdviserAuthResponse, PuntoAsesoria } from '@/types/adviser';

type ApiEnvelope<T> = {
    data: T;
};

type ModalPuntoAsesoria = {
    numero: number;
    oficina_afiliacion: string;
    code_oficina: string;
    nombre_usuario: string;
    email: string;
    estado: string;
};

export function useAdviser() {
    const { postJson } = useApi();
    const { isAuthenticated, setSession } = useSession();

    const username = ref('');
    const password = ref('');
    const loading = ref(false);
    const errorMsg = ref('');

    const showPuntosModal = ref(false);
    const puntosAsesoria = ref<ModalPuntoAsesoria[]>([]);
    const selectedPunto = ref<ModalPuntoAsesoria | null>(null);
    const authData = ref<AdviserAuthResponse | null>(null);

    const validateForm = (): boolean => {
        errorMsg.value = '';

        if (!username.value.trim()) {
            errorMsg.value = 'El nombre de usuario es requerido';
            return false;
        }

        if (!password.value) {
            errorMsg.value = 'La contraseña es requerida';
            return false;
        }

        if (password.value.length < 6) {
            errorMsg.value = 'La contraseña debe tener al menos 6 caracteres';
            return false;
        }

        return true;
    };

    const normalizePuntosAsesoria = (raw: PuntoAsesoria[]): ModalPuntoAsesoria[] => {
        return raw.map((p: PuntoAsesoria) => ({
            numero: Number(p.numero),
            oficina_afiliacion: p.oficina_afiliacion,
            code_oficina: p.code_oficina,
            nombre_usuario: p.nombre_usuario,
            email: p.email,
            estado: p.estado
        }));
    };

    const completeLogin = async (data: AdviserAuthResponse): Promise<boolean> => {
        const accessToken = String(data?.access_token || '');
        const tokenType = String(data?.token_type || 'bearer');
        const user = data?.user;

        if (!accessToken) {
            throw new Error('Respuesta inválida de autenticación');
        }

        setSession({
            accessToken,
            tokenType,
            user: {
                id: 0,
                username: typeof user?.username === 'string' ? user.username : username.value,
                roles: Array.isArray(user?.roles) ? user.roles : [],
                permissions: Array.isArray(user?.permissions) ? user.permissions : [],
                email: typeof user?.email === 'string' ? user.email : '',
                tipo_documento: typeof user?.tipo_documento === 'string' ? user.tipo_documento : '',
                numero_documento: typeof user?.numero_documento === 'string' ? user.numero_documento : '',
                nombres: typeof user?.nombres === 'string' ? user.nombres : '',
                apellidos: typeof user?.apellidos === 'string' ? user.apellidos : '',
                full_name: `${typeof user?.nombres === 'string' ? user.nombres : ''} ${typeof user?.apellidos === 'string' ? user.apellidos : ''}`.trim(),
                adviser_number: '',
                asesor: user?.asesor,
                trabajador: user?.trabajador ?? null,
                selected_punto: null
            }
        });

        router.visit('/inicio');
        return true;
    };

    const login = async (): Promise<boolean> => {
        if (!validateForm()) return false;

        loading.value = true;
        errorMsg.value = '';

        try {
            const response = await postJson<ApiEnvelope<AdviserAuthResponse>>('/api/auth/adviser/autenticar', {
                username: username.value,
                password: password.value
            });

            const data = response.data;
            authData.value = data;

            const userRoles = Array.isArray(data?.user?.roles) ? data.user.roles : [];
            if (!userRoles.includes('adviser')) {
                throw new Error('El usuario no tiene rol de asesor');
            }

            const puntos = Array.isArray(data?.user?.puntos_asesorias) ? data.user.puntos_asesorias : [];
            if (puntos.length > 0) {
                puntosAsesoria.value = normalizePuntosAsesoria(puntos);
                showPuntosModal.value = true;
                return true;
            }

            return await completeLogin(data);
        } catch (e: unknown) {
            const message = e instanceof Error ? e.message : 'No fue posible iniciar sesión como asesor';
            errorMsg.value = message;
            return false;
        } finally {
            loading.value = false;
        }
    };

    const checkAuthAndRedirect = async (): Promise<void> => {
        if (isAuthenticated.value) {
            router.visit('/inicio');
        }
    };

    const selectPuntoAsesoria = async (punto: ModalPuntoAsesoria): Promise<void> => {
        selectedPunto.value = punto;
        showPuntosModal.value = false;

        if (authData.value) {
            await completeLogin(authData.value);
        }
    };

    const cancelPuntoSelection = (): void => {
        showPuntosModal.value = false;
        selectedPunto.value = null;
        authData.value = null;
    };

    return {
        username,
        password,
        loading,
        errorMsg,
        login,
        checkAuthAndRedirect,
        validateForm,
        showPuntosModal,
        puntosAsesoria,
        selectPuntoAsesoria,
        cancelPuntoSelection
    };
}
