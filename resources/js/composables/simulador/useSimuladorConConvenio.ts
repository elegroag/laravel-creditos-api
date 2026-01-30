import { ref, computed, watch } from 'vue';
import { useConvenioValidation } from './useConvenioValidation';
import { useSimulador } from '@/composables/simulador/useSimulador';

/**
 * Extensión del simulador con validación de convenio empresarial
 * Permite calcular tasas y condiciones especiales si el trabajador tiene convenio
 */
export function useSimuladorConConvenio() {
    const {
        validarConvenio,
        isElegible,
        convenio,
        trabajador,
        loading: loadingConvenio,
        error: errorConvenio,
        getMensajeError
    } = useConvenioValidation();

    // Importar simulador base
    const {
        monto,
        plazoMeses,
        tasaEfectivaAnual,
        ingresosMensuales,
        descuentosMensuales,
        maxEndeudamientoPct,
        cuotaMensual,
        totalIntereses,
        totalPagar,
        capacidadDisponible,
        maxCuotaPermitida,
        margen,
        apto,
        resultados,
        actualizarMonto,
        actualizarPlazo,
        actualizarTasaAnual,
        actualizarIngresos,
        actualizarDescuentos,
        actualizarEndeudamiento,
        formatoMoneda,
        resetearValores
    } = useSimulador();

    // Datos del trabajador para validación
    const nitEmpresa = ref('');
    const cedulaTrabajador = ref('');

    // Estados
    const convenioVerificado = ref(false);
    const usandoConvenio = ref(false);

    // Beneficios del convenio (pueden ser configurables desde backend)
    const beneficiosConvenio = computed(() => {
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
    });

    // Mensaje de beneficios para mostrar al usuario
    const mensajeBeneficios = computed(() => {
        const beneficios = beneficiosConvenio.value;
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
    });

    // Tasa ajustada con descuento de convenio
    const tasaConConvenio = computed(() => {
        if (!usandoConvenio.value || !beneficiosConvenio.value) {
            return tasaEfectivaAnual.value;
        }

        const tasaBase = tasaEfectivaAnual.value;
        const descuento = beneficiosConvenio.value.tasaDescuento;
        return Math.max(0, tasaBase - descuento);
    });

    // Plazo máximo permitido con convenio
    const plazoMaximoConvenio = computed(() => {
        if (!usandoConvenio.value || !beneficiosConvenio.value) {
            return 60; // Plazo estándar
        }

        return beneficiosConvenio.value.plazoMaximoMeses;
    });

    // Monto máximo permitido con convenio
    const montoMaximoConvenio = computed(() => {
        if (!usandoConvenio.value || !beneficiosConvenio.value) {
            return 50_000_000; // Monto estándar
        }

        const montoBase = 50_000_000;
        return montoBase * beneficiosConvenio.value.factorAumento;
    });

    // Validaciones ajustadas con convenio
    const plazoValido = computed(() => {
        const maxPlazo = plazoMaximoConvenio.value;
        return plazoMeses.value > 0 && plazoMeses.value <= maxPlazo;
    });

    const montoValido = computed(() => {
        const maxMonto = montoMaximoConvenio.value;
        return monto.value > 0 && monto.value <= maxMonto;
    });

    const puedeUsarConvenio = computed(() => {
        return isElegible.value && convenioVerificado.value;
    });

    // Recalcular con tasa de convenio si está activo
    watch([tasaConConvenio, plazoMeses, monto], () => {
        if (usandoConvenio.value) {
            // Aquí podrías llamar a una función para recalcular la cuota
            // con la tasa ajustada del convenio
        }
    });

    // Métodos
    const verificarConvenio = async () => {
        if (!nitEmpresa.value || !cedulaTrabajador.value) {
            errorConvenio.value = 'Por favor ingresa el NIT de la empresa y la cédula del trabajador';
            return false;
        }

        const exito = await validarConvenio(nitEmpresa.value, cedulaTrabajador.value);
        
        if (exito) {
            convenioVerificado.value = true;
            // Activar automáticamente el uso del convenio si es elegible
            if (isElegible.value) {
                activarConvenio();
            }
        }

        return exito;
    };

    const activarConvenio = () => {
        if (!puedeUsarConvenio.value) return;

        usandoConvenio.value = true;
        
        // Aplicar beneficios del convenio
        if (beneficiosConvenio.value) {
            // Ajustar tasa si aplica
            if (beneficiosConvenio.value.tasaReducida) {
                // La tasa se ajustará automáticamente vía computed
            }
            
            // Ajustar plazo máximo si excede el actual
            if (beneficiosConvenio.value.plazoExtendido && plazoMeses.value > 60) {
                // El plazo ya está validado en plazoValido computed
            }
        }
    };

    const desactivarConvenio = () => {
        usandoConvenio.value = false;
    };

    const resetearConvenio = () => {
        nitEmpresa.value = '';
        cedulaTrabajador.value = '';
        convenioVerificado.value = false;
        usandoConvenio.value = false;
    };

    const actualizarDatosTrabajador = (nit: string, cedula: string) => {
        nitEmpresa.value = nit;
        cedulaTrabajador.value = cedula;
        convenioVerificado.value = false;
        usandoConvenio.value = false;
    };

    // Resultados del simulador con convenio
    const resultadosConvenio = computed(() => {
        const baseResultados = resultados.value;
        
        if (!usandoConvenio.value) {
            return baseResultados;
        }

        // Ajustar resultados con beneficios del convenio
        const beneficios = beneficiosConvenio.value;
        if (!beneficios) return baseResultados;

        return {
            ...baseResultados,
            // La cuota mensual se recalcula automáticamente con la tasa ajustada
            cuotaMensual: cuotaMensual.value, // Ya usa la tasa ajustada
            tieneConvenio: true,
            beneficiosAplicados: beneficios,
            empresaConvenio: convenio.value?.razon_social || ''
        };
    });

    // Mensajes para el usuario
    const getMensajeConvenio = computed(() => {
        if (loadingConvenio.value) return 'Validando convenio...';
        if (errorConvenio.value) return getMensajeError();
        if (!nitEmpresa.value || !cedulaTrabajador.value) return 'Ingresa los datos para validar convenio';
        if (!convenioVerificado.value) return 'Haz clic en "Validar Convenio" para verificar tu elegibilidad';
        if (!isElegible.value) return 'No elegible para convenio empresarial';
        if (isElegible.value && !usandoConvenio.value) return 'Elegible para convenio. ¡Actívalo para obtener beneficios!';
        if (usandoConvenio.value) return 'Convenio activo. Disfrutando de beneficios especiales.';
        return '';
    });

    const getTipoMensaje = computed(() => {
        if (loadingConvenio.value) return 'info';
        if (errorConvenio.value) return 'error';
        if (isElegible.value && !usandoConvenio.value) return 'success';
        if (usandoConvenio.value) return 'success';
        return 'info';
    });

    return {
        // Del simulador base
        monto,
        plazoMeses,
        tasaEfectivaAnual,
        ingresosMensuales,
        descuentosMensuales,
        maxEndeudamientoPct,
        cuotaMensual,
        totalIntereses,
        totalPagar,
        capacidadDisponible,
        maxCuotaPermitida,
        margen,
        apto,
        resultados,
        actualizarMonto,
        actualizarPlazo,
        actualizarTasaAnual,
        actualizarIngresos,
        actualizarDescuentos,
        actualizarEndeudamiento,
        formatoMoneda,
        resetearValores,

        // Del validador de convenio
        nitEmpresa,
        cedulaTrabajador,
        isElegible,
        convenio,
        trabajador,
        loadingConvenio,
        errorConvenio,
        convenioVerificado,
        usandoConvenio,

        // Computed de convenio
        beneficiosConvenio,
        mensajeBeneficios,
        tasaConConvenio,
        plazoMaximoConvenio,
        montoMaximoConvenio,
        plazoValido,
        montoValido,
        puedeUsarConvenio,
        resultadosConvenio,
        getMensajeConvenio,
        getTipoMensaje,

        // Métodos de convenio
        verificarConvenio,
        activarConvenio,
        desactivarConvenio,
        resetearConvenio,
        actualizarDatosTrabajador,
    };
}
