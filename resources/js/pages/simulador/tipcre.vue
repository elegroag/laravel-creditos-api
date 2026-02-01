<template>
    <DashboardLayout>
        <div class="mx-auto max-w-5xl p-4 sm:p-8">
            <div class="mb-8">
                <div class="flex items-center gap-4 mb-4">
                    <Button variant="ghost" size="sm" @click="goToLineas" class="text-muted-foreground">
                        ← Volver a líneas de crédito
                    </Button>
                </div>

                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold text-foreground mb-2">Simulador de crédito</h1>
                        <p class="text-muted-foreground">Estima la cuota mensual, intereses y capacidad de pago.</p>
                    </div>

                    <div class="flex items-center gap-3 ml-auto">
                        <div v-if="lineaSeleccionada" class="text-right">
                            <Badge
                                :variant="lineaSeleccionada.estado === 'A' ? 'default' : 'secondary'"
                                class="text-sm mb-1"
                            >
                                {{ lineaSeleccionada.detalle }}
                            </Badge>
                            <div class="text-sm">
                                <Badge
                                    :variant="lineaSeleccionada.estado === 'A' ? 'secondary' : 'outline'"
                                    class="text-xs"
                                >
                                    {{ lineaSeleccionada.estado === 'A' ? 'Activo' : 'Inactivo' }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="flex justify-center items-center min-h-[400px]">
                <div class="text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
                    <p class="text-muted-foreground">Cargando línea de crédito...</p>
                </div>
            </div>

            <div v-else-if="error" class="flex justify-center items-center min-h-[400px]">
                <Card class="border-destructive/50 bg-destructive/5 max-w-md">
                    <CardContent class="p-6 text-center">
                        <AlertCircle class="h-12 w-12 text-destructive mx-auto mb-4" />
                        <h3 class="text-lg font-semibold mb-2">Error al cargar</h3>
                        <p class="text-muted-foreground">{{ error }}</p>
                        <div class="flex gap-3 mt-4">
                            <Button @click="cargarLineaCredito">Reintentar</Button>
                            <Button variant="outline" @click="goToLineas">Volver a líneas</Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-else-if="lineaSeleccionada && lineaSeleccionada.estado !== 'A'" class="mb-6">
                <Card class="border-orange-200 bg-orange-50">
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 bg-orange-500 rounded-full"></div>
                            <p class="text-sm text-orange-800">
                                <strong>{{ lineaSeleccionada.detalle }}</strong> se encuentra temporalmente inactiva.
                                No es posible solicitar este crédito en este momento.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="convenioVerificado && isElegible && lineaSeleccionada?.estado === 'A'" class="mb-6">
                <ConvenioAlert
                    :titulo="'🎉 ¡Elegible para Crédito Convenio Empresarial!'"
                    :descripcion="'Su empresa tiene convenio con COMFACA. Beneficios: Tasa reducida, plazo extendido y más.'"
                    tipo="success"
                    :dismissible="false"
                />
            </div>

            <div v-else-if="convenioVerificado && !isElegible && convenioMensajeError && lineaSeleccionada?.estado === 'A'" class="mb-6">
                <ConvenioAlert
                    :titulo="'Validación de convenio'"
                    :descripcion="convenioMensajeError"
                    tipo="info"
                />
            </div>

            <div v-if="lineaSeleccionada?.estado === 'A'" class="grid gap-6 lg:grid-cols-2">
                <Card class="border-primary/20">
                    <CardHeader>
                        <CardTitle>Datos del crédito</CardTitle>
                        <CardDescription>Ingresa la información para calcular tu crédito</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-2">
                            <Label for="monto">Monto (COP)</Label>
                            <Input
                                id="monto"
                                type="number"
                                v-model.number="monto"
                                class="text-base"
                                step="10000"
                                min="0"
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="plazo">Plazo (meses)</Label>
                                <Input
                                    id="plazo"
                                    type="number"
                                    v-model.number="plazoMeses"
                                    class="text-base"
                                    step="1"
                                    min="1"
                                    :max="lineaSeleccionada?.numcuo || 999"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Plazo máximo: {{ lineaSeleccionada?.numcuo || 'N/A' }} meses
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label>Tipo de tasa</Label>
                                <div class="flex gap-4">
                                    <div class="flex items-center space-x-2">
                                        <input
                                            id="tasaAnual"
                                            type="radio"
                                            :checked="tipoTasa === 'anual'"
                                            @change="cambiarTipoTasa('anual')"
                                            class="text-primary"
                                        />
                                        <Label for="tasaAnual" class="text-sm font-normal cursor-pointer">Anual (EA)</Label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input
                                            id="tasaMensual"
                                            type="radio"
                                            :checked="tipoTasa === 'mensual'"
                                            @change="cambiarTipoTasa('mensual')"
                                            class="text-primary"
                                        />
                                        <Label for="tasaMensual" class="text-sm font-normal cursor-pointer">Mensual</Label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="tasa">
                                {{ tipoTasa === 'anual' ? 'Tasa efectiva anual (EA %)' : 'Tasa mensual (%)' }}
                            </Label>
                            <Input
                                id="tasa"
                                type="number"
                                step="0.1"
                                v-model.number="tasaInput"
                                class="text-base"
                                min="0"
                                :readonly="Boolean(categoriaTrabajador && lineaSeleccionada?.categorias?.length)"
                            />
                            <p v-if="categoriaTrabajador && lineaSeleccionada?.categorias?.length" class="text-xs text-muted-foreground">
                                💡 Tasa aplicada automáticamente según categoría del trabajador
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="ingresos">Ingresos mensuales <br /><small>Salario bruto.</small></Label>
                                <Input
                                    id="ingresos"
                                    type="number"
                                    v-model.number="ingresosMensuales"
                                    class="text-base"
                                    step="10000"
                                    min="0"
                                    :readonly="Boolean(trabajadorSession?.salario)"
                                />
                                <p v-if="trabajadorSession?.salario" class="text-xs text-muted-foreground">
                                    💡 Salario del trabajador cargado automáticamente
                                </p>
                                <p class="text-xs text-muted-foreground">Ingreso neto (92%): {{ fmt(ingresosSan) }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="descuentos">Descuentos mensuales <small>Obligaciones adquiridas.</small></Label>
                                <Input
                                    id="descuentos"
                                    type="number"
                                    v-model.number="descuentosMensuales"
                                    class="text-base"
                                    step="10000"
                                    min="0"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="maxEndeudamiento">Máximo endeudamiento por ley (50%)</Label>
                            <Input
                                id="maxEndeudamiento"
                                type="number"
                                v-model.number="maxEndeudamientoPct"
                                class="text-base"
                                min="0"
                                max="100"
                            />
                            <p class="text-xs text-muted-foreground">
                                Porcentaje de la capacidad disponible que se permite destinar a la cuota.
                            </p>
                        </div>

                        <Card class="border-border">
                            <CardHeader>
                                <CardTitle class="text-base">Convenio empresarial (opcional)</CardTitle>
                                <CardDescription>Valida si aplicas a beneficios por convenio</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="nit">NIT empresa</Label>
                                        <Input id="nit" v-model="nitEmpresa" placeholder="Ej: 900123456" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="cedula">Cédula trabajador</Label>
                                        <Input id="cedula" v-model="cedulaTrabajador" placeholder="Ej: 1234567890" />
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <Button variant="outline" :disabled="loadingConvenio" @click="verificarConvenio">
                                        {{ loadingConvenio ? 'Validando...' : 'Validar convenio' }}
                                    </Button>
                                    <Button variant="ghost" @click="limpiarConvenio">Limpiar</Button>
                                </div>
                            </CardContent>
                        </Card>
                    </CardContent>
                </Card>

                <div class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <Card class="border-primary/30 bg-linear-to-br from-primary/5 to-primary/10">
                            <CardHeader class="pb-3">
                                <CardDescription class="text-xs font-medium uppercase tracking-wider">Cuota estimada</CardDescription>
                                <CardTitle class="text-3xl font-bold text-primary">{{ fmt(cuotaMensual) }}</CardTitle>
                                <p class="text-xs text-muted-foreground">Tasa mensual: {{ fmtPct(tasaMensual * 100) }}</p>
                            </CardHeader>
                        </Card>

                        <Card class="border-secondary/30 bg-linear-to-br from-secondary/5 to-secondary/10">
                            <CardHeader class="pb-3">
                                <CardDescription class="text-xs font-medium uppercase tracking-wider">
                                    Capacidad disponible <small>(Descuento en Nómina)</small>
                                </CardDescription>
                                <CardTitle class="text-3xl font-bold text-secondary">{{ fmt(capacidadDisponible) }}</CardTitle>
                                <p class="text-xs text-muted-foreground">Máximo cuota: {{ fmt(maxCuotaPermitida) }}</p>
                            </CardHeader>
                        </Card>
                    </div>

                    <Card class="border-primary/20">
                        <CardHeader class="pb-3">
                            <CardDescription class="text-xs font-medium uppercase tracking-wider">Total a pagar</CardDescription>
                            <CardTitle class="text-2xl font-bold">{{ fmt(totalPagar) }}</CardTitle>
                            <p class="text-sm text-muted-foreground">Intereses estimados: {{ fmt(intereses) }}</p>
                        </CardHeader>
                    </Card>

                    <Card :class="`border-2 ${apto ? 'border-secondary/50 bg-secondary/5' : 'border-destructive/50 bg-destructive/5'}`">
                        <CardHeader>
                            <div class="flex items-start gap-3">
                                <CheckCircle2 v-if="apto" class="h-5 w-5 text-secondary mt-0.5" />
                                <AlertCircle v-else class="h-5 w-5 text-destructive mt-0.5" />
                                <div>
                                    <CardTitle class="text-base mb-1">Evaluación rápida</CardTitle>
                                    <CardDescription :class="apto ? 'text-secondary' : 'text-destructive'">
                                        {{ apto ? 'La cuota está dentro del límite' : 'La cuota excede tu capacidad de pago' }}
                                    </CardDescription>
                                    <p class="text-sm text-muted-foreground mt-2">
                                        {{ apto ? `Margen: ${fmt(margen)}` : `Exceso: ${fmt(Math.abs(margen))}` }}
                                    </p>
                                </div>
                            </div>
                        </CardHeader>
                    </Card>

                    <div class="flex flex-wrap gap-3">
                        <Button class="flex-1 min-w-[220px]" size="lg" @click="goToSolicitud">Continuar con solicitud</Button>
                        <Button variant="outline" size="lg" @click="goToLineas">Volver</Button>
                        <Button variant="secondary" size="lg" @click="resetAll">Restablecer</Button>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2 } from 'lucide-vue-next';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import Badge from '@/components/ui/Badge.vue';
import ConvenioAlert from '@/components/solicitud/ConvenioAlert.vue';
import { useSession } from '@/composables/useSession';
import { useConvenioValidation } from '@/composables/solicitud/useConvenioValidation';
import { useSimuladorWithLinea } from '@/composables/simulador/useSimuladorWithLinea';
import { useSimuladorStorage } from '@/composables/useSimuladorStorage';
import type { LineaCreditoData, SimuladorStorageData } from '@/types/simulador';
import type { Trabajador } from '@/types/trabajador';

interface PageProps {
    tipcre: string;
}

const page = usePage<PageProps & { lineaCreditoData?: any; lineaSeleccionada?: any }>();
const tipcre = typeof page.props.tipcre === 'string' ? page.props.tipcre : '';
const lineaCreditoData = computed(() => page.props.lineaCreditoData as any);
const lineaSeleccionadaFromServer = computed(() => page.props.lineaSeleccionada as any);

const { user } = useSession();

const trabajadorSession = computed<Trabajador | null>(() => user.value?.trabajador ?? null);
const categoriaTrabajador = computed(() => trabajadorSession.value?.codigo_categoria);

const loading = ref(true);
const error = ref<string | null>(null);
const lineaSeleccionada = ref<LineaCreditoData | null>(null);
const lineasCache = ref<Map<string, LineaCreditoData>>(new Map());

const {
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
} = useSimuladorWithLinea(lineaSeleccionada);

const {
    validationRequest,
    loading: loadingConvenio,
    isElegible,
    isValidated,
    validarConvenio,
    limpiarValidacion,
    getMensajeError
} = useConvenioValidation();

const nitEmpresa = computed({
    get: () => validationRequest.value.nit,
    set: (v: string) => {
        validationRequest.value.nit = v;
    }
});

const cedulaTrabajador = computed({
    get: () => validationRequest.value.cedula_trabajador,
    set: (v: string) => {
        validationRequest.value.cedula_trabajador = v;
    }
});

const convenioVerificado = computed(() => Boolean(isValidated.value));
const convenioMensajeError = computed(() => {
    if (!convenioVerificado.value) return null;
    if (isElegible.value) return null;
    const msg = getMensajeError();
    return msg ? String(msg) : null;
});

const { saveSimuladorDataSilent, clearSimuladorData } = useSimuladorStorage();

const goToLineas = () => {
    router.visit('/web/simulador/lineas-credito');
};

const goToSolicitud = () => {
    router.visit('/solicitud');
};

const limpiarConvenio = () => {
    limpiarValidacion();
};

const verificarConvenio = async () => {
    await validarConvenio();
};

const cargarLineaCredito = () => {
        try {
            loading.value = true;
            error.value = null;

            if (!tipcre) {
                error.value = 'Línea de crédito no encontrada';
                return;
            }

            // Usar datos del servidor
            const data = lineaCreditoData.value;
            const lineaFromServer = lineaSeleccionadaFromServer.value;

            if (data?.success && lineaFromServer) {
                lineaSeleccionada.value = lineaFromServer;

                // Cargar en caché
                if (Array.isArray(data.data)) {
                    data.data.forEach((linea: any) => {
                        lineasCache.value.set(linea.tipcre, linea);
                    });
                }
            } else {
                error.value = data?.message || 'Línea de crédito no encontrada';
                return;
            }

            if (trabajadorSession.value?.salario) {
                ingresosMensuales.value = trabajadorSession.value.salario;
            }

            if (categoriaTrabajador.value) {
                setTasaEAFromCategoria(categoriaTrabajador.value);
            }

            if (trabajadorSession.value?.empresa?.nit && trabajadorSession.value.cedula) {
                nitEmpresa.value = trabajadorSession.value.empresa.nit;
                cedulaTrabajador.value = trabajadorSession.value.cedula;
                validarConvenio(nitEmpresa.value, cedulaTrabajador.value);
            }
        } catch (err: any) {
            const message = err instanceof Error ? err.message : 'No se pudo cargar la línea de crédito.';
            error.value = message;
        } finally {
            loading.value = false;
        }
    };

const tasaInput = computed({
    get: () => (tipoTasa.value === 'anual' ? tasaEfectivaAnual.value : tasaMensualInput.value),
    set: (value: number) => {
        if (tipoTasa.value === 'anual') {
            tasaEfectivaAnual.value = value;
        } else {
            tasaMensualInput.value = value;
        }
    }
});

let saveTimeout: ReturnType<typeof setTimeout> | null = null;

watch(
    [
        monto,
        plazoMeses,
        tasaEfectivaAnual,
        ingresosMensuales,
        descuentosMensuales,
        maxEndeudamientoPct,
        cuotaMensual,
        totalPagar,
        intereses,
        lineaSeleccionada,
        isElegible,
        isValidated
    ],
    () => {
        if (saveTimeout) {
            clearTimeout(saveTimeout);
        }

        saveTimeout = setTimeout(() => {
            if (!lineaSeleccionada.value) return;
            if (monto.value <= 0) return;

            const payload: SimuladorStorageData = {
                lineaCredito: lineaSeleccionada.value,
                monto: montoSan.value,
                montoCredito: montoSan.value,
                plazoMeses: plazoMesesSan.value,
                tasaEfectivaAnual: tasaEASan.value,
                ingresosMensuales: ingresosBrutosSan.value,
                descuentosMensuales: descuentosSan.value,
                maxEndeudamientoPct: maxEndeudamientoPct.value,
                tasaInteresAnual: tasaEASan.value,
                cuotaMensual: cuotaMensual.value,
                totalIntereses: intereses.value,
                totalPagar: totalPagar.value,
                fechaSimulacion: new Date().toISOString(),
                tieneConvenio: isElegible.value,
                convenioVerificado: isValidated.value,
                nitEmpresa: nitEmpresa.value,
                cedulaTrabajador: cedulaTrabajador.value
            };

            saveSimuladorDataSilent(payload);
        }, 500);
    },
    { deep: true }
);

const resetAll = () => {
    reset();
    clearSimuladorData();
    limpiarValidacion();
};

onMounted(() => {
        cargarLineaCredito();
    });
</script>
