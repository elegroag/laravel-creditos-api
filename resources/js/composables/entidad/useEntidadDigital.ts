import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useApi } from '@/composables/useApi';
import { useStorage } from '@/composables/useStorage';
import { useSession } from '@/composables/useSession';
import type { 
    TipoIdentificacionExtendido, 
    EntidadDigitalData,
    EntidadValidationResponse,
    CapturaRequest,
    CapturaResponse,
    VerificacionResponse,
    TokenValidationResponse,
    EntidadDigitalSession,
    QrCodeState
} from '@/types/entidad';

export function useEntidadDigital() {
    const { postJson, getJson } = useApi();
    const { isAuthenticated } = useSession();
    const storage = useStorage();

    // Estado del QR
    const qrCodeState = ref<QrCodeState>({
        url: '',
        loading: false,
        error: null,
        expired: false,
        timeRemaining: 0,
        timeRemainingClass: '',
        isCapturasConfirmadas: false,
        socketResult: null
    });

    // Form state
    const tipoIdentificacion = ref<TipoIdentificacionExtendido>('CC');
    const numeroIdentificacion = ref('');
    const clave = ref('');
    const claveConfirm = ref('');
    const overwrite = ref(false);

    // UI state
    const loading = ref(false);
    const error = ref('');
    const result = ref<EntidadDigitalData | null>(null);
    const success = ref(false);

    // Flow state
    const currentStep = ref<'basic' | 'qr'>('basic');
    const redirectTo = ref('');

    // Computed
    const isBasicFormValid = computed(() => {
        return tipoIdentificacion.value && 
               numeroIdentificacion.value && 
               numeroIdentificacion.value.length >= 7;
    });

    const isQrFormValid = computed(() => {
        return qrCodeState.value.url && !qrCodeState.value.error && !qrCodeState.value.expired;
    });

    const isPasswordFormValid = computed(() => {
        return clave.value && 
               claveConfirm.value && 
               clave.value === claveConfirm.value &&
               clave.value.length >= 8;
    });

    const isFormComplete = computed(() => {
        if (currentStep.value === 'basic') {
            return isBasicFormValid.value;
        }
        return isQrFormValid.value;
    });

    // Métodos de validación
    const validateIdentificacion = async () => {
        if (!isBasicFormValid.value) {
            throw new Error('Por favor complete los datos básicos de identificación');
        }

        loading.value = true;
        error.value = '';

        try {
            const response = await postJson<EntidadValidationResponse>('/api/entidad/validar', {
                tipo_identificacion: tipoIdentificacion.value,
                numero_identificacion: numeroIdentificacion.value
            }, { auth: true });

            if (response.valido) {
                result.value = response.datos;
                return response.datos;
            } else {
                throw new Error(response.mensaje || 'Identificación no válida');
            }
        } catch (err: any) {
            console.error('Error validando identificación:', err);
            error.value = err.message || 'Error al validar la identificación';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const validatePassword = () => {
        if (!isPasswordFormValid.value) {
            throw new Error('Las contraseñas no coinciden o son muy cortas');
        }
    };

    // Métodos de QR
    const generateQR = async () => {
        if (!result.value) {
            throw new Error('Debe validar la identificación primero');
        }

        qrCodeState.value.loading = true;
        qrCodeState.value.error = null;

        try {
            const response = await postJson<CapturaResponse>('/api/entidad/captura', {
                tipo_identificacion: tipoIdentificacion.value,
                numero_identificacion: numeroIdentificacion.value,
                callback_url: `${window.location.origin}/entidad/verificar`
            }, { auth: true });

            qrCodeState.value.url = response.qr_code;
            qrCodeState.value.expires_at = response.expires_at;

            // Iniciar polling para verificar estado del QR
            startQrPolling(response.session_id);

            return response;
        } catch (err: any) {
            console.error('Error generando QR:', err);
            qrCode.value.error = err.message || 'Error al generar el código QR';
            qrCodeState.value.loading = false;
            throw err;
        } finally {
            qrCodeState.value.loading = false;
        }
    };

    const startQrPolling = (sessionId: string) => {
        const checkQR = async () => {
            try {
                const response = await getJson<TokenValidationResponse>(`/api/entidad/qr/status/${sessionId}`, { auth: true });

                if (response.expirado) {
                    qrCodeState.value.expired = true;
                    qrState.value.timeRemaining = 0;
                    qrState.value.timeRemainingClass = 'expired';
                    stopQrPolling();
                } else {
                    qrCodeState.value.expired = false;
                    qrState.value.timeRemaining = response.tiempo_restante;
                    qrState.value.timeRemainingClass = getTimeRemainingClass(response.tiempo_restante);
                }
            } catch (err: any) {
                console.error('Error verificando QR:', err);
                qrCodeState.value.error = err.message || 'Error verificando estado del QR';
                stopQrPolling();
            }
        };

        // Iniciar polling
        const interval = setInterval(checkQR, 5000);
        qrCodeState.value.socketResult = interval;

        // Detener polling después de 5 minutos
        setTimeout(() => {
            stopQrPolling();
        }, 5 * 60 * 1000);
    };

    const stopQrPolling = () => {
        if (qrCodeState.value.socketResult) {
            clearInterval(qrCodeState.value.socketResult);
            qrCodeState.value.socketResult = null;
        }
    };

    const getTimeRemainingClass = (seconds: number): string => {
        if (seconds <= 60) return 'text-green-600';
        if (seconds <= 300) return 'text-yellow-600';
        if (seconds <= 600) return 'text-orange-600';
        return 'text-red-600';
    };

    const formatTimeRemaining = (seconds: number): string => {
        if (seconds <= 60) return `${seconds}s`;
        const minutes = Math.floor(seconds / 60);
        return `${minutes}m`;
    };

    // Métodos de verificación
    const verificarToken = async (sessionId: string) => {
        try {
            const response = await getJson<VerificacionResponse>(`/api/entidad/verificar/${sessionId}`, { auth: true });

            if (response.valido) {
                result.value = response.datos;
                success.value = true;
                
                // Guardar en sesión
                await storage.setItem('entidad_session', JSON.stringify({
                    session_id: sessionId,
                    datos: response.datos,
                    timestamp: response.timestamp
                }));

                // Redirigir si hay una URL de destino
                if (redirectTo.value) {
                    router.visit(redirectTo.value);
                }
            } else {
                throw new Error(response.mensaje || 'Verificación fallida');
            }
        } catch (err: any) {
            console.error('Error verificando token:', err);
            error.value = err.message || 'Error en la verificación';
            throw err;
        }
    };

    const verificarCapturas = async () => {
        if (!qrCodeState.value.url) return;

        try {
            // Simular verificación de capturas biométricas
            const response = await postJson('/api/entidad/verificar-capturas', {
                session_id: qrCodeState.value.url.split('/').pop(),
                imagenes_capturadas: []
            }, { auth: true });

            qrCode.value.isCapturasConfirmadas = response.valido;
        } catch (err: any) {
            console.error('Error verificando capturas:', err);
            qrCodeState.value.isCapturasConfirmadas = false;
        }
    };

    // Métodos de flujo
    const nextStep = () => {
        if (currentStep.value === 'basic') {
            currentStep.value = 'qr';
        } else if (currentStep.value === 'qr') {
            currentStep.value = 'basic';
        }
    };

    const prevStep = () => {
        if (currentStep.value === 'qr') {
            currentStep.value = 'basic';
        } else if (currentStep.value === 'basic') {
            currentStep.value = 'qr';
        }
    };

    const submitForm = async () => {
        try {
            error.value = '';
            success.value = false;

            if (currentStep.value === 'basic') {
                await validateIdentificacion();
                nextStep();
            } else if (currentStep.value === 'qr') {
                await generateQR();
                success.value = true;
            }
        } catch (err: any) {
            error.value = err.message;
        }
    };

    const resetForm = () => {
        tipoIdentificacion.value = 'CC';
        numeroIdentificacion.value = '';
        clave.value = '';
        claveConfirm.value = '';
        overwrite.value = false;
        error.value = '';
        success.value = false;
        result.value = null;
        currentStep.value = 'basic';
        redirectTo.value = '';
        
        // Limpiar estado QR
        qrCodeState.value = {
            url: '',
            loading: false,
            error: null,
            expired: false,
            timeRemaining: 0,
            timeRemainingClass: '',
            isCapturasConfirmadas: false,
            socketResult: null
        };
        
        stopQrPolling();
    };

    // Métodos de utilidad
    const getTipoIdentificacionLabel = (tipo: string): string => {
        const labels: Record<TipoIdentificacionExtendido, string> = {
            'CC': 'Cédula de Ciudadanía',
            'CE': 'Cédula de Extranjería',
            'NIT': 'Número de Identificación Tributaria',
            'TI': 'Tarjeta de Identidad',
            'PP': 'Pasaporte',
            'SS': 'Salvo Social',
            'RC': 'Registro Civil',
            'CE': 'Cédula de Extranjería',
            'PASAPORTE': 'Pasaporte'
        };
        return labels[tipo] || tipo;
    };

    const formatIdentificacion = (tipo: string, numero: string): string => {
        if (tipo === 'NIT') {
            // Formateo NIT colombiano: XXX.XXX.XXX-X
            const clean = numero.replace(/[^\d]/g, '');
            if (clean.length <= 8) {
                return clean;
            }
            return `${clean.slice(0, 8)}.${clean.slice(8, 11)}.${clean.slice(11)}`;
        }
        return numero;
    };

    const formatNumero = (value: string): string => {
        // Formatear número con separadores de miles
        return value.replace(/\B(?=(\d{3})+(?!\d))/g, '$1.');
    };

    // Métodos de sesión
    const clearSessionData = async () => {
        try {
            await storage.removeItem('entidad_session');
            qrCodeState.value = {
                url: '',
                loading: false,
                error: null,
                expired: false,
                timeRemaining: 0,
                timeRemainingClass: '',
                isCapturasConfirmadas: false,
                socketResult: null
            };
        } catch (err: any) {
            console.error('Error limpiando sesión de entidad:', err);
        }
    };

    // Inicialización
    const initialize = async () => {
        // Verificar si hay una sesión activa
        try {
            const sessionData = await storage.getItem('entidad_session');
            if (sessionData) {
                const session = JSON.parse(sessionData);
                
                // Verificar si la sesión aún es válida
                const response = await getJson<TokenValidationResponse>(`/api/entidad/session/status/${session.session_id}`, { auth: true });
                
                if (response.valido && !response.expirado) {
                    result.value = session.datos;
                    currentStep.value = 'qr';
                    qrCodeState.value.url = 'placeholder'; // Se debería obtener de la sesión
                    success.value = true;
                } else {
                    await clearSessionData();
                }
            }
        } catch (err: any) {
            console.error('Error inicializando sesión de entidad:', err);
            await clearSessionData();
        }
    };

    // Cleanup
    const cleanup = () => {
        stopQrPolling();
    };

    return {
        // Estado
        tipoIdentificacion,
        numeroIdentificacion,
        clave,
        claveConfirm,
        overwrite,
        loading,
        error,
        result,
        success,
        currentStep,
        redirectTo,
        qrCodeState,

        // Computed
        isBasicFormValid,
        isQrFormValid,
        isPasswordFormValid,
        isFormComplete,

        // Métodos principales
        validateIdentificacion,
        validatePassword,
        generateQR,
        verificarToken,
        verificarCapturas,

        // Métodos de flujo
        nextStep,
        prevStep,
        submitForm,
        resetForm,

        // Métodos de utilidad
        getTipoIdentificacionLabel,
        formatIdentificacion,
        formatNumero,

        // Métodos de sesión
        clearSessionData,

        // Inicialización y cleanup
        initialize,
        cleanup
    };
}
