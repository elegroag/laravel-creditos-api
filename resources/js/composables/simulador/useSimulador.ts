import { computed, ref } from 'vue';
import type { SimuladorData, SimuladorResultados } from '@/types/simulador';

export function useSimulador() {
    // Valores por defecto
    const monto = ref(5_000_000);
    const plazoMeses = ref(36);
    const tasaEfectivaAnual = ref(24);
    const tasaMensualInput = ref(2);
    const tipoTasa = ref<'anual' | 'mensual'>('anual');
    const ingresosMensuales = ref(2_500_000);
    const descuentosMensuales = ref(500_000);
    const maxEndeudamientoPct = ref(50);

    // Función helper para validar números
    const _num = (v: unknown): number => {
        const n = typeof v === 'number' ? v : Number(v);
        return Number.isFinite(n) ? n : 0;
    };

    // Computed properties con validación y saneamiento
    const montoSan = computed(() => Math.max(0, _num(monto.value)));
    const plazoMesesSan = computed(() => Math.max(1, Math.floor(_num(plazoMeses.value) || 1)));
    const tasaEASan = computed(() => Math.max(0, _num(tasaEfectivaAnual.value)));
    const tasaMensualSan = computed(() => Math.max(0, _num(tasaMensualInput.value)));

    const ingresosBrutosSan = computed(() => Math.max(0, _num(ingresosMensuales.value)));
    const descuentosSan = computed(() => Math.max(0, _num(descuentosMensuales.value)));

    const capacidadPagoMaxima = computed(() => {
        const bruto = ingresosBrutosSan.value;
        // Capacidad de Pago Mensual Máximo endeudamiento (50%) por default, del salario menos el 8% de descuentos de Ley
        return (bruto * maxEndeudamientoSan.value / 100) - (bruto * 0.08);
    });

    const ingresosSan = computed(() => {
        // Ingresos netos aproximados para cálculos internos si fuera necesario
        return ingresosBrutosSan.value * 0.92;
    });

    const maxEndeudamientoSan = computed(() => {
        const v = _num(maxEndeudamientoPct.value);
        return Math.min(100, Math.max(0, v));
    });

    // Cálculos financieros
    const tasaMensual = computed(() => {
        if (tipoTasa.value === 'mensual') {
            // Si el usuario selecciona tasa mensual, usar directamente ese valor
            return tasaMensualSan.value / 100;
        } else {
            // Convertir tasa efectiva anual a tasa mensual
            const tasaEA = tasaEASan.value / 100;
            return Math.pow(1 + tasaEA, 1 / 12) - 1;
        }
    });

    const cuotaMensual = computed(() => {
        const principal = montoSan.value;
        const tasa = tasaMensual.value;
        const n = plazoMesesSan.value;

        if (tasa === 0) {
            return principal / n;
        }

        // Fórmula de cuota fija: P = (r * M) / (1 - (1 + r)^-n)
        return (tasa * principal) / (1 - Math.pow(1 + tasa, -n));
    });

    const totalIntereses = computed(() => {
        const totalPagado = cuotaMensual.value * plazoMesesSan.value;
        return Math.max(0, totalPagado - montoSan.value);
    });

    const totalPagar = computed(() => {
        return cuotaMensual.value * plazoMesesSan.value;
    });

    const capacidadDisponible = computed(() => {
        return Math.max(0, capacidadPagoMaxima.value - descuentosSan.value);
    });

    const maxCuotaPermitida = computed(() => {
        return Math.max(0, capacidadPagoMaxima.value);
    });

    const margen = computed(() => {
        return Math.max(0, maxCuotaPermitida.value - cuotaMensual.value);
    });

    const apto = computed(() => {
        return cuotaMensual.value <= maxCuotaPermitida.value;
    });

    // Resultados del simulador
    const resultados = computed<SimuladorResultados>(() => ({
        cuotaMensual: cuotaMensual.value,
        totalPagar: totalPagar.value,
        intereses: totalIntereses.value,
        capacidadDisponible: capacidadDisponible.value,
        maxCuotaPermitida: maxCuotaPermitida.value,
        margen: margen.value,
        apto: apto.value,
    }));

    // Métodos de utilidad
    const resetearValores = () => {
        monto.value = 5_000_000;
        plazoMeses.value = 36;
        tasaEfectivaAnual.value = 24;
        tasaMensualInput.value = 2;
        tipoTasa.value = 'anual';
        ingresosMensuales.value = 2_500_000;
        descuentosMensuales.value = 500_000;
        maxEndeudamientoPct.value = 50;
    };

    const actualizarMonto = (valor: number) => {
        monto.value = valor;
    };

    const actualizarPlazo = (valor: number) => {
        plazoMeses.value = valor;
    };

    const actualizarTasaAnual = (valor: number) => {
        tasaEfectivaAnual.value = valor;
    };

    const actualizarTasaMensual = (valor: number) => {
        tasaMensualInput.value = valor;
    };

    const actualizarIngresos = (valor: number) => {
        ingresosMensuales.value = valor;
    };

    const actualizarDescuentos = (valor: number) => {
        descuentosMensuales.value = valor;
    };

    const actualizarEndeudamiento = (valor: number) => {
        maxEndeudamientoPct.value = valor;
    };

    const cambiarTipoTasa = (tipo: 'anual' | 'mensual') => {
        tipoTasa.value = tipo;
    };

    // Formateo de valores para mostrar
    const formatoMoneda = (valor: number): string => {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(valor);
    };

    const formatoPorcentaje = (valor: number): string => {
        return `${valor.toFixed(2)}%`;
    };

    // Datos para almacenamiento
    const getStorageData = (): SimuladorData => ({
        monto: montoSan.value,
        plazoMeses: plazoMesesSan.value,
        tasaEfectivaAnual: tasaEASan.value,
        ingresosMensuales: ingresosBrutosSan.value,
        descuentosMensuales: descuentosSan.value,
        maxEndeudamientoPct: maxEndeudamientoSan.value,
    });

    const cargarDesdeStorage = (data: Partial<SimuladorData>) => {
        if (data.monto) monto.value = data.monto;
        if (data.plazoMeses) plazoMeses.value = data.plazoMeses;
        if (data.tasaEfectivaAnual) tasaEfectivaAnual.value = data.tasaEfectivaAnual;
        if (data.ingresosMensuales) ingresosMensuales.value = data.ingresosMensuales;
        if (data.descuentosMensuales) descuentosMensuales.value = data.descuentosMensuales;
        if (data.maxEndeudamientoPct) maxEndeudamientoPct.value = data.maxEndeudamientoPct;
    };

    return {
        // Estado reactivo
        monto,
        plazoMeses,
        tasaEfectivaAnual,
        tasaMensualInput,
        tipoTasa,
        ingresosMensuales,
        descuentosMensuales,
        maxEndeudamientoPct,

        // Computed properties
        montoSan,
        plazoMesesSan,
        tasaEASan,
        tasaMensualSan,
        ingresosBrutosSan,
        descuentosSan,
        capacidadPagoMaxima,
        ingresosSan,
        maxEndeudamientoSan,

        // Cálculos financieros
        tasaMensual,
        cuotaMensual,
        totalIntereses,
        totalPagar,
        capacidadDisponible,
        maxCuotaPermitida,
        margen,
        apto,

        // Resultados
        resultados,

        // Métodos
        resetearValores,
        actualizarMonto,
        actualizarPlazo,
        actualizarTasaAnual,
        actualizarTasaMensual,
        actualizarIngresos,
        actualizarDescuentos,
        actualizarEndeudamiento,
        cambiarTipoTasa,

        // Formateo
        formatoMoneda,
        formatoPorcentaje,

        // Storage
        getStorageData,
        cargarDesdeStorage,
    };
}
