import type { LineaCreditoData } from '@/types/simulador';

export type TipoTasa = 'anual' | 'mensual';

export function useSimuladorCore() {
    const _num = (v: unknown): number => {
        const n = typeof v === 'number' ? v : Number(v);
        return Number.isFinite(n) ? n : 0;
    };

    const calcularMontoSan = (monto: number): number => Math.max(0, _num(monto));

    const calcularPlazoSan = (plazo: number): number => Math.max(1, Math.floor(_num(plazo) || 1));

    const calcularTasaEASan = (tasaEA: number): number => Math.max(0, _num(tasaEA));

    const calcularTasaMensualSan = (tasaMensual: number): number => Math.max(0, _num(tasaMensual));

    const calcularIngresosBrutosSan = (ingresos: number): number => Math.max(0, _num(ingresos));

    const calcularDescuentosSan = (descuentos: number): number => Math.max(0, _num(descuentos));

    const calcularMaxEndeudamientoSan = (maxEndeudamientoPct: number): number => {
        const v = _num(maxEndeudamientoPct);
        return Math.min(100, Math.max(0, v));
    };

    const calcularIngresosSan = (ingresosBrutos: number): number => ingresosBrutos * 0.92;

    const calcularCapacidadPagoMaxima = (ingresosBrutos: number, maxEndeudamientoPct: number): number => {
        const bruto = calcularIngresosBrutosSan(ingresosBrutos);
        const maxEnd = calcularMaxEndeudamientoSan(maxEndeudamientoPct);
        return (bruto * maxEnd / 100) - (bruto * 0.08);
    };

    const calcularTasaMensual = (tasaEASan: number, tasaMensualSan: number, tipoTasa: TipoTasa): number => {
        if (tipoTasa === 'mensual') {
            return tasaMensualSan / 100;
        }

        const ea = tasaEASan / 100;
        if (ea <= 0) return 0;
        return Math.pow(1 + ea, 1 / 12) - 1;
    };

    const calcularCuotaMensual = (monto: number, plazo: number, tasaMensual: number): number => {
        const P = calcularMontoSan(monto);
        const n = calcularPlazoSan(plazo);
        const r = tasaMensual;

        if (P <= 0 || n <= 0) return 0;
        if (r <= 0) return P / n;

        const denom = 1 - Math.pow(1 + r, -n);
        if (denom <= 0) return 0;

        return (P * r) / denom;
    };

    const calcularTotalPagar = (cuotaMensual: number, plazo: number): number => {
        return cuotaMensual * calcularPlazoSan(plazo);
    };

    const calcularIntereses = (totalPagar: number, monto: number): number => {
        return Math.max(0, totalPagar - calcularMontoSan(monto));
    };

    const calcularCapacidadDisponible = (ingresosBrutos: number, descuentos: number, maxEndeudamientoPct: number): number => {
        const capacidadMax = calcularCapacidadPagoMaxima(ingresosBrutos, maxEndeudamientoPct);
        const descSan = calcularDescuentosSan(descuentos);
        return Math.max(0, capacidadMax - descSan);
    };

    const calcularMargen = (capacidadDisponible: number, cuotaMensual: number): number => {
        return capacidadDisponible - cuotaMensual;
    };

    const calcularApto = (cuotaMensual: number, capacidadDisponible: number): boolean => {
        return cuotaMensual <= capacidadDisponible;
    };

    const convertirAnualAMensual = (tasaEA: number): number => {
        const ea = tasaEA / 100;
        if (ea <= 0) return 0;
        return (Math.pow(1 + ea, 1 / 12) - 1) * 100;
    };

    const convertirMensualAAnual = (tasaMensual: number): number => {
        const tm = tasaMensual / 100;
        if (tm <= 0) return 0;
        return (Math.pow(1 + tm, 12) - 1) * 100;
    };

    const fmt = (value: number): string => {
        const n = Number.isFinite(value) ? value : 0;
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            maximumFractionDigits: 0
        }).format(n);
    };

    const fmtPct = (value: number): string => {
        const n = Number.isFinite(value) ? value : 0;
        return `${n.toFixed(2)}%`;
    };

    const getTasaEAFromCategoria = (linea: LineaCreditoData | null, codigoCategoria?: string): number | null => {
        if (!linea?.categorias?.length) return null;
        if (!codigoCategoria) return null;

        const categoriaTrabajador = String(codigoCategoria).toLowerCase();
        const categoriaLinea = linea.categorias.find((cat) => {
            const code = (cat?.codcat ?? '').toString().toLowerCase();
            return code === categoriaTrabajador;
        });

        if (!categoriaLinea?.facfin) return null;

        const tasa = Number(categoriaLinea.facfin);
        return Number.isFinite(tasa) ? tasa : null;
    };

    return {
        calcularMontoSan,
        calcularPlazoSan,
        calcularTasaEASan,
        calcularTasaMensualSan,
        calcularIngresosBrutosSan,
        calcularDescuentosSan,
        calcularMaxEndeudamientoSan,
        calcularIngresosSan,
        calcularCapacidadPagoMaxima,
        calcularTasaMensual,
        calcularCuotaMensual,
        calcularTotalPagar,
        calcularIntereses,
        calcularCapacidadDisponible,
        calcularMargen,
        calcularApto,
        convertirAnualAMensual,
        convertirMensualAAnual,
        fmt,
        fmtPct,
        getTasaEAFromCategoria
    };
}
