import { computed, ref, watch, type Ref } from 'vue';
import type { LineaCreditoData } from '@/types/simulador';
import { useSimuladorCore, type TipoTasa } from '@/composables/simulador/useSimuladorCore';

export function useSimuladorWithLinea(lineaCredito: Ref<LineaCreditoData | null>) {
    const {
        calcularCuotaMensual,
        calcularTotalPagar,
        calcularIntereses,
        calcularCapacidadDisponible,
        calcularMargen,
        calcularApto,
        calcularTasaMensual,
        fmt,
        fmtPct,
        calcularMontoSan,
        calcularPlazoSan,
        calcularTasaEASan,
        calcularTasaMensualSan,
        calcularIngresosBrutosSan,
        calcularDescuentosSan,
        calcularIngresosSan,
        convertirAnualAMensual,
        convertirMensualAAnual,
        getTasaEAFromCategoria
    } = useSimuladorCore();

    const monto = ref(5_000_000);
    const plazoMeses = ref(36);
    const tasaEfectivaAnual = ref(24);
    const tasaMensualInput = ref(2);
    const tipoTasa = ref<TipoTasa>('anual');
    const ingresosMensuales = ref(2_500_000);
    const descuentosMensuales = ref(0);
    const maxEndeudamientoPct = ref(50);

    watch(
        lineaCredito,
        (nuevaLinea) => {
            if (nuevaLinea?.numcuo) {
                plazoMeses.value = nuevaLinea.numcuo;
            }
        },
        { immediate: true }
    );

    const montoSan = computed(() => calcularMontoSan(monto.value));
    const plazoMesesSan = computed(() => calcularPlazoSan(plazoMeses.value));
    const tasaEASan = computed(() => calcularTasaEASan(tasaEfectivaAnual.value));
    const tasaMensualSan = computed(() => calcularTasaMensualSan(tasaMensualInput.value));
    const ingresosBrutosSan = computed(() => calcularIngresosBrutosSan(ingresosMensuales.value));
    const descuentosSan = computed(() => calcularDescuentosSan(descuentosMensuales.value));
    const ingresosSan = computed(() => calcularIngresosSan(ingresosBrutosSan.value));

    const tasaMensual = computed(() => calcularTasaMensual(tasaEASan.value, tasaMensualSan.value, tipoTasa.value));

    const cuotaMensual = computed(() => calcularCuotaMensual(monto.value, plazoMeses.value, tasaMensual.value));

    const totalPagar = computed(() => calcularTotalPagar(cuotaMensual.value, plazoMeses.value));

    const intereses = computed(() => calcularIntereses(totalPagar.value, monto.value));

    const capacidadDisponible = computed(() =>
        calcularCapacidadDisponible(ingresosBrutosSan.value, descuentosMensuales.value, maxEndeudamientoPct.value)
    );

    const maxCuotaPermitida = computed(() => capacidadDisponible.value);
    const margen = computed(() => calcularMargen(capacidadDisponible.value, cuotaMensual.value));
    const apto = computed(() => calcularApto(cuotaMensual.value, capacidadDisponible.value));

    const cambiarTipoTasa = (nuevoTipo: TipoTasa) => {
        if (nuevoTipo === tipoTasa.value) return;

        if (tipoTasa.value === 'anual' && nuevoTipo === 'mensual') {
            const tasaMensualConvertida = convertirAnualAMensual(tasaEASan.value);
            tasaMensualInput.value = Number(tasaMensualConvertida.toFixed(2));
        }

        if (tipoTasa.value === 'mensual' && nuevoTipo === 'anual') {
            const tasaAnualConvertida = convertirMensualAAnual(tasaMensualSan.value);
            tasaEfectivaAnual.value = Number(tasaAnualConvertida.toFixed(2));
        }

        tipoTasa.value = nuevoTipo;
    };

    const setTasaEAFromCategoria = (codigoCategoria?: string) => {
        const tasaEA = getTasaEAFromCategoria(lineaCredito.value, codigoCategoria);
        if (typeof tasaEA === 'number') {
            tasaEfectivaAnual.value = tasaEA;
        }
    };

    const reset = () => {
        monto.value = 5_000_000;
        plazoMeses.value = lineaCredito.value?.numcuo || 36;
        tasaEfectivaAnual.value = 24;
        tasaMensualInput.value = 2;
        tipoTasa.value = 'anual';
        ingresosMensuales.value = 2_500_000;
        descuentosMensuales.value = 0;
        maxEndeudamientoPct.value = 50;
    };

    return {
        monto,
        plazoMeses,
        tasaEfectivaAnual,
        tasaMensualInput,
        tipoTasa,
        ingresosMensuales,
        descuentosMensuales,
        maxEndeudamientoPct,
        montoSan,
        plazoMesesSan,
        tasaEASan,
        tasaMensualSan,
        ingresosSan,
        ingresosBrutosSan,
        descuentosSan,
        tasaMensual,
        cuotaMensual,
        totalPagar,
        intereses,
        capacidadDisponible,
        maxCuotaPermitida,
        margen,
        apto,
        fmt,
        fmtPct,
        cambiarTipoTasa,
        setTasaEAFromCategoria,
        reset
    };
}
