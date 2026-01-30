import { ref, computed } from 'vue';
import { useApi } from '@/composables/useApi';
import { useSession } from '@/composables/useSession';
import type { ConvenioValidationRequest, ConvenioValidationResponse } from '@/types/convenios';

export function useConvenioValidation() {
    const { postJson } = useApi();
    const { isAuthenticated } = useSession();

    // Estado
    const validationRequest = ref<ConvenioValidationRequest>({
        nit: '',
        cedula_trabajador: '',
    });

    const validationResponse = ref<ConvenioValidationResponse | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const isValidated = ref(false);

    // Computed properties
    const isElegible = computed(() => {
        return validationResponse.value?.valido || false;
    });

    const convenio = computed(() => {
        return validationResponse.value?.empresa || null;
    });

    const trabajador = computed(() => {
        return validationResponse.value?.trabajador || null;
    });

    const mensajeExito = computed(() => {
        return validationResponse.value?.mensaje || '';
    });

    // Métodos
    const validarConvenio = async (nit?: string, cedulaTrabajador?: string) => {
        if (!isAuthenticated.value) {
            error.value = 'Usuario no autenticado';
            return false;
        }

        const request: ConvenioValidationRequest = {
            nit: nit || validationRequest.value.nit,
            cedula_trabajador: cedulaTrabajador || validationRequest.value.cedula_trabajador,
        };

        if (!request.nit || !request.cedula_trabajador) {
            error.value = 'Por favor ingresa el NIT de la empresa y la cédula del trabajador';
            return false;
        }

        loading.value = true;
        error.value = null;

        try {
            const response = await postJson<ConvenioValidationResponse>('/api/convenios/validar', request, { auth: true });
            
            validationResponse.value = response;
            isValidated.value = true;
            
            return response.valido;
        } catch (err: any) {
            console.error('Error validando convenio:', err);
            error.value = err.message || 'Error al validar el convenio';
            validationResponse.value = null;
            isValidated.value = false;
            return false;
        } finally {
            loading.value = false;
        }
    };

    const actualizarDatos = (nit: string, cedulaTrabajador: string) => {
        validationRequest.value.nit = nit;
        validationRequest.value.cedula_trabajador = cedulaTrabajador;
        isValidated.value = false;
        validationResponse.value = null;
        error.value = null;
    };

    const limpiarValidacion = () => {
        validationRequest.value = {
            nit: '',
            cedula_trabajador: '',
        };
        validationResponse.value = null;
        isValidated.value = false;
        error.value = null;
    };

    const getMensajeError = (): string => {
        if (error.value) return error.value;
        
        if (!validationResponse.value) return '';
        
        if (!validationResponse.value.valido) {
            return validationResponse.value.mensaje || 'No elegible para convenio';
        }
        
        return '';
    };

    const getBeneficiosConvenio = () => {
        if (!isElegible.value || !convenio.value) return null;

        return {
            tasaReducida: true,
            tasaDescuento: 0.5, // 0.5% de descuento
            plazoExtendido: true,
            plazoMaximoMeses: 72, // vs 60 estándar
            montoMaximoAumentado: true,
            factorAumento: 1.2, // 20% más de monto
            requisitosFlexibles: true,
            tramiteRapido: true,
            empresaNombre: convenio.value.razon_social
        };
    };

    const getMensajeBeneficios = () => {
        const beneficios = getBeneficiosConvenio();
        if (!beneficios) return null;

        return {
            titulo: '🎉 ¡Elegible para Crédito Convenio Empresarial!',
            items: [
                `Tasa de interés reducida con ${beneficios.tasaDescuento}% de descuento`,
                `Plazo extendido hasta ${beneficios.plazoMaximoMeses} meses`,
                `Monto máximo aumentado en ${((beneficios.factorAumento - 1) * 100).toFixed(0)}%`,
                'Requisitos flexibles y trámite rápido',
                `Empresa: ${beneficios.empresaNombre}`
            ],
            color: 'green'
        };
    };

    // Utilidades
    const formatoNit = (nit: string): string => {
        // Formatear NIT colombiano: XXX.XXX.XXX-X
        const cleanNit = nit.replace(/[^\d]/g, '');
        if (cleanNit.length <= 9) return cleanNit;
        
        const formatted = cleanNit.slice(0, 9) + '-' + cleanNit.slice(9, 10);
        return formatted.slice(0, 4) + '.' + formatted.slice(4, 7) + '.' + formatted.slice(7, 11) + '-' + formatted.slice(11);
    };

    const formatoCedula = (cedula: string): string => {
        // Formatear cédula colombiana: XXX.XXX.XXX-X
        const cleanCedula = cedula.replace(/[^\d]/g, '');
        if (cleanCedula.length <= 9) return cleanCedula;
        
        const formatted = cleanCedula.slice(0, 9) + '-' + cleanCedula.slice(9, 10);
        return formatted.slice(0, 3) + '.' + formatted.slice(3, 6) + '.' + formatted.slice(6, 10) + '-' + formatted.slice(10);
    };

    const validarFormatoNit = (nit: string): boolean => {
        const cleanNit = nit.replace(/[^\d]/g, '');
        return cleanNit.length >= 9 && cleanNit.length <= 10;
    };

    const validarFormatoCedula = (cedula: string): boolean => {
        const cleanCedula = cedula.replace(/[^\d]/g, '');
        return cleanCedula.length >= 7 && cleanCedula.length <= 10;
    };

    return {
        // Estado
        validationRequest,
        validationResponse,
        loading,
        error,
        isValidated,

        // Computed
        isElegible,
        convenio,
        trabajador,
        mensajeExito,

        // Métodos principales
        validarConvenio,
        actualizarDatos,
        limpiarValidacion,
        getMensajeError,

        // Beneficios
        getBeneficiosConvenio,
        getMensajeBeneficios,

        // Utilidades
        formatoNit,
        formatoCedula,
        validarFormatoNit,
        validarFormatoCedula,
    };
}
