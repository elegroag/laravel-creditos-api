<template>
    <DashboardLayout>
        <div class="mx-auto max-w-5xl p-4 sm:p-8">
            <Card class="mb-6 border-border shadow-sm">
                <CardContent class="p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h1 class="text-2xl font-semibold tracking-tight text-foreground">Bienvenido</h1>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ session.user?.username || 'Usuario' }}
                                <span v-if="(session.user?.roles || []).length" class="text-muted-foreground/60">·</span>
                                <span v-if="(session.user?.roles || []).length" class="text-muted-foreground">
                                    {{ (session.user?.roles || []).join(', ') }}
                                </span>
                            </p>
                            <p class="mt-3 text-sm text-muted-foreground">Selecciona una opción para comenzar.</p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <Link href="/web/simulador/lineas-credito">
                                <Button class="bg-primary text-primary-foreground hover:bg-primary/90">
                                    <FilePlus class="h-5 w-5 mr-2" />
                                    Nueva solicitud
                                </Button>
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="mb-6 border-border shadow-sm">
                <CardContent class="p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-start gap-3 flex-1">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border border-border bg-card p-2">
                                <ClipboardList class="h-full w-full text-foreground" />
                            </div>
                            <div class="flex-1">
                                <div class="text-base font-semibold text-foreground">Mis solicitudes</div>
                                <div class="mt-1 text-sm text-muted-foreground">Listado de tus solicitudes y estado actual.</div>

                                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                    <span class="font-semibold text-foreground">Flujo:</span>
                                    <div v-if="loadingEstados" class="text-muted-foreground">Cargando estados...</div>
                                    <template v-else-if="estadosError" class="text-destructive">
                                        <span>{{ estadosError }}</span>
                                        <Button variant="ghost" size="sm" @click="cargarEstados" class="ml-1 p-0 h-auto text-xs">
                                            Reintentar
                                        </Button>
                                    </template>
                                    <template v-else>
                                        <template v-for="(estado, i) in estadosConColores" :key="estado.nombre">
                                            <div class="flex items-center gap-1.5">
                                                <div
                                                    class="flex items-center gap-1 px-2 py-1 rounded-md border"
                                                    :style="{
                                                        borderColor: estado.color + '40',
                                                        backgroundColor: estado.color + '20',
                                                        color: estado.color
                                                    }"
                                                >
                                                    <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: estado.color }"></div>
                                                    <span class="font-medium">{{ estado.nombre }}</span>
                                                </div>
                                                <ChevronRight v-if="i < estadosConColores.length - 1" class="h-3.5 w-3.5 text-muted-foreground/40" />
                                            </div>
                                        </template>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <Button
                            variant="outline"
                            @click="cargarSolicitudes"
                            :disabled="loadingSolicitudes"
                            class="shrink-0 bg-transparent"
                        >
                            <RefreshCw :class="['h-5 w-5 mr-2', loadingSolicitudes ? 'animate-spin' : '']" />
                            Actualizar
                        </Button>
                    </div>

                    <div class="mt-4">
                        <div v-if="loadingSolicitudes" class="text-sm text-muted-foreground">Cargando solicitudes...</div>
                        <div v-else-if="solicitudesError" class="text-sm text-destructive">{{ solicitudesError }}</div>
                        <div v-else-if="solicitudes.length === 0" class="rounded-md border border-border bg-muted/30 p-4 text-sm text-muted-foreground">
                            Aún no tienes solicitudes registradas.
                        </div>
                        <div v-else class="overflow-hidden rounded-md border border-border">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-muted/50 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th class="px-4 py-3">Modalidad</th>
                                        <th class="px-4 py-3">Monto</th>
                                        <th class="px-4 py-3">Estado</th>
                                        <th class="px-4 py-3">Creación</th>
                                        <th class="px-4 py-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="s in solicitudes" :key="s.id" class="border-t border-border">
                                        <td class="px-4 py-3 text-foreground">{{ (s as any).payload?.linea_credito?.detalle_modalidad || '-' }}</td>
                                        <td class="px-4 py-3 text-foreground">{{ fmtMoney((s as any)?.payload?.solicitud?.valor_solicitud || s.monto_solicitado || 0) }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-col gap-2">
                                                <Badge :class="`w-fit ${estadoBadgeClass(String(s.estado || ''))}`">
                                                    {{ getEstadoInfo(String(s.estado || '')).nombre || s.estado || '-' }}
                                                </Badge>
                                                <Progress :model-value="estadoProgressPercent(String(s.estado || ''))" class="w-32 h-1.5" />
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-foreground">{{ fmtDate((s as any).created_at || s.fecha_radicado || s.fecha_actualizacion) }}</td>
                                        <td class="px-4 py-3">
                                            <Link :href="`/solicitudes/${s.id}`">
                                                <Button variant="outline" size="sm" class="gap-1">
                                                    <Eye class="h-4 w-4" />
                                                    Ver
                                                </Button>
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-4 sm:grid-cols-2">
                <Link
                    href="/web/simulador/lineas-credito"
                    class="group block rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-card/80"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-md border border-border bg-card p-2">
                                <Calculator class="h-full w-full text-foreground" />
                            </div>
                            <div class="min-w-0">
                                <div class="text-base font-semibold text-foreground group-hover:text-primary">Simulador de crédito</div>
                                <div class="mt-1 text-sm text-muted-foreground">Cuota mensual, tasa efectiva y capacidad de endeudamiento</div>
                            </div>
                        </div>
                        <ChevronRight class="h-5 w-5 shrink-0 text-muted-foreground group-hover:text-primary" />
                    </div>
                </Link>

                <Link
                    href="/solicitud"
                    class="group block rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-card/80"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-md border border-border bg-card p-2">
                                <FilePlus class="h-full w-full text-foreground" />
                            </div>
                            <div class="min-w-0">
                                <div class="text-base font-semibold text-foreground group-hover:text-primary">Solicitud de crédito</div>
                                <div class="mt-1 text-sm text-muted-foreground">Captura secuencial por bloques y generación de XML</div>
                            </div>
                        </div>
                        <ChevronRight class="h-5 w-5 shrink-0 text-muted-foreground group-hover:text-primary" />
                    </div>
                </Link>

                <Link
                    v-if="!esTrabajador"
                    href="/firmas"
                    class="group block rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-card/80"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-md border border-border bg-card p-2">
                                <PenTool class="h-full w-full text-foreground" />
                            </div>
                            <div class="min-w-0">
                                <div class="text-base font-semibold text-foreground group-hover:text-primary">Firmas</div>
                                <div class="mt-1 text-sm text-muted-foreground">Firmar y visualizar solicitudes</div>
                            </div>
                        </div>
                        <ChevronRight class="h-5 w-5 shrink-0 text-muted-foreground group-hover:text-primary" />
                    </div>
                </Link>

                <Link
                    v-if="!esTrabajador"
                    href="/firmas-compartir"
                    class="group block rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:border-primary/50 hover:bg-card/80"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-md border border-border bg-card p-2">
                                <Share2 class="h-full w-full text-foreground" />
                            </div>
                            <div class="min-w-0">
                                <div class="text-base font-semibold text-foreground group-hover:text-primary">Compartir firmas</div>
                                <div class="mt-1 text-sm text-muted-foreground">Generar enlaces y QR para firma digital</div>
                            </div>
                        </div>
                        <ChevronRight class="h-5 w-5 shrink-0 text-muted-foreground group-hover:text-primary" />
                    </div>
                </Link>
            </div>
        </div>
    </DashboardLayout>
</template>

<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Calculator, PenTool, Share2, ChevronRight, RefreshCw, ClipboardList, FilePlus, Eye } from 'lucide-vue-next';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Badge from '@/components/ui/Badge.vue';
import Progress from '@/components/ui/Progress.vue';
import { useSession } from '@/composables/useSession';
import { useInicio } from '@/composables/inicio/useInicio';

const { session } = useSession();

const {
    solicitudes,
    loadingSolicitudes,
    solicitudesError,
    flujoAprobacion,
    fmtMoney,
    fmtDate,
    cargarSolicitudes,
    cargarFlujoAprobacion,
    cargarEstados,
    estadosError,
    loadingEstados,
    estadoProgressPercent,
    estadoBadgeClass,
    getEstadoInfo,
    getEstadoColor,
} = useInicio();

const esTrabajador = computed(() => {
    return session.value?.user?.roles?.includes('user_trabajador') || false;
});

const estadosConColores = computed(() => {
    return flujoAprobacion.value.map((estado) => ({
        nombre: estado.nombre,
        color: getEstadoColor(estado.nombre),
        data: estado
    }));
});

onMounted(async () => {
    await Promise.all([cargarFlujoAprobacion(), cargarSolicitudes()]);
});
</script>
