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
                            <Link href="/simulador/lineas-credito">
                                <Button class="bg-primary text-primary-foreground hover:bg-primary/90">
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
                                <span class="text-foreground">S</span>
                            </div>
                            <div class="flex-1">
                                <div class="text-base font-semibold text-foreground">Mis solicitudes</div>
                                <div class="mt-1 text-sm text-muted-foreground">Listado de tus solicitudes y estado actual.</div>
                            </div>
                        </div>

                        <Button
                            variant="outline"
                            @click="cargarSolicitudes"
                            :disabled="loadingSolicitudes"
                            class="shrink-0 bg-transparent"
                        >
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
                                        <th class="px-4 py-3">Monto</th>
                                        <th class="px-4 py-3">Estado</th>
                                        <th class="px-4 py-3">Actualización</th>
                                        <th class="px-4 py-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="s in solicitudes" :key="s.id" class="border-t border-border">
                                        <td class="px-4 py-3 text-foreground">{{ fmtMoney(s.monto_solicitado) }}</td>
                                        <td class="px-4 py-3">
                                            <Badge class="w-fit">
                                                {{ s.estado || '-' }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-3 text-foreground">{{ fmtDate(s.fecha_actualizacion) }}</td>
                                        <td class="px-4 py-3">
                                            <Link :href="`/solicitudes/${s.id}`">
                                                <Button variant="outline" size="sm">Ver</Button>
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </DashboardLayout>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Badge from '@/components/ui/Badge.vue';
import { useSession } from '@/composables/useSession';
import { useInicio } from '@/composables/inicio/useInicio';

const { session } = useSession();

const {
    solicitudes,
    loadingSolicitudes,
    solicitudesError,
    fmtMoney,
    fmtDate,
    cargarSolicitudes,
    cargarFlujoAprobacion
} = useInicio();

onMounted(async () => {
    await Promise.all([cargarFlujoAprobacion(), cargarSolicitudes()]);
});
</script>
