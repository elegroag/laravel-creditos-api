<template>
    <DashboardLayout>
        <div class="mx-auto max-w-7xl p-4 sm:p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-foreground mb-2">Líneas de Crédito</h1>
                <p class="text-muted-foreground">Explora nuestras líneas de crédito organizadas por modalidad.</p>
            </div>

            <div v-if="loading" class="flex justify-center items-center min-h-[400px]">
                <div class="text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
                    <p class="text-muted-foreground">Cargando líneas de crédito...</p>
                </div>
            </div>

            <div v-else-if="error" class="flex justify-center items-center min-h-[400px]">
                <Card class="border-destructive/50 bg-destructive/5 max-w-md">
                    <CardContent class="p-6 text-center">
                        <AlertCircle class="h-12 w-12 text-destructive mx-auto mb-4" />
                        <h3 class="text-lg font-semibold mb-2">Error al cargar</h3>
                        <p class="text-muted-foreground">{{ error }}</p>
                        <Button @click="router.reload()" class="mt-4">Reintentar</Button>
                    </CardContent>
                </Card>
            </div>

            <div v-else class="space-y-8">
                <div v-for="modalidad in modalidadesAgrupadas" :key="modalidad.modxml4" class="space-y-4">
                    <div class="flex items-center gap-4 p-4 bg-linear-to-r from-primary/10 to-primary/5 rounded-lg border border-primary/20">
                        <div class="flex items-center gap-3">
                            <div class="p-3 bg-primary/20 rounded-full">
                                <component :is="modalidad.icon" class="h-8 w-8 text-primary" />
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-foreground">{{ modalidad.nombre }}</h2>
                                <p class="text-sm text-muted-foreground">{{ modalidad.descripcion }}</p>
                            </div>
                        </div>
                        <Badge variant="secondary" class="ml-auto">
                            {{ modalidad.lineas.length }} líneas disponibles
                        </Badge>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Card
                            v-for="linea in modalidad.lineas"
                            :key="linea.tipcre"
                            class="hover:shadow-lg transition-shadow cursor-pointer border-primary/20"
                            @click="seleccionarLinea(linea)"
                        >
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <CardTitle class="text-base mb-1">{{ linea.detalle }}</CardTitle>
                                        <CardDescription class="text-xs">Código: {{ linea.tipcre }}</CardDescription>
                                    </div>
                                    <Badge :variant="linea.estado === 'A' ? 'default' : 'secondary'" class="text-xs">
                                        {{ linea.estado === 'A' ? 'Activo' : 'Inactivo' }}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent class="pt-0">
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Plazo máximo:</span>
                                        <span class="font-medium">{{ linea.numcuo }} meses</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Estudio crédito:</span>
                                        <span class="font-medium">{{ fmtMoney(linea.estcre) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Seguro requerido:</span>
                                        <span class="font-medium">{{ linea.pagseg === 'S' ? 'Sí' : 'No' }}</span>
                                    </div>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="w-full mt-4"
                                    @click.stop="simularLinea(linea)"
                                >
                                    Simular esta línea
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Banknote,
    ShoppingCart,
    GraduationCap,
    Heart,
    Home,
    Briefcase,
    MoreHorizontal,
    TrendingUp,
    Plane
} from 'lucide-vue-next';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import CardContent from '@/components/ui/CardContent.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import Badge from '@/components/ui/Badge.vue';

interface LineaCredito {
    tipcre: string;
    codcre: string;
    detalle: string;
    modxml4: number;
    tipfin: string;
    pagseg: string;
    codcap: string;
    codint: string;
    codmor: string;
    repdcr: string;
    codcon: string;
    codser: string;
    numcuo: number;
    estcre: number;
    auxest: string;
    estado: string;
    codcen: string;
}

type Modalidad = {
    modxml4: number;
    nombre: string;
    descripcion: string;
    icon: typeof Banknote;
    lineas: LineaCredito[];
};

const page = usePage();
const lineasCreditoData = computed(() => page.props.lineasCreditoData as any);

const loading = ref(false);
const error = computed(() => {
    const data = lineasCreditoData.value;
    if (!data?.success) {
        return data?.message || 'Error al cargar las líneas de crédito';
    }
    return null;
});

const lineasCredito = computed<LineaCredito[]>(() => {
    const data = lineasCreditoData.value;
    return Array.isArray(data?.data) ? data.data : [];
});

const modalidadesMap: Record<number, { nombre: string; descripcion: string; icon: typeof Banknote }> = {
    1: {
        nombre: 'Crédito Libre Inversión',
        descripcion: 'Flexibilidad total para usar los fondos como necesites',
        icon: Banknote
    },
    2: {
        nombre: 'Consumo de Bienes y Servicios',
        descripcion: 'Financia la compra de bienes y servicios para tu hogar',
        icon: ShoppingCart
    },
    3: {
        nombre: 'Créditos Educativos',
        descripcion: 'Invierte en tu educación y desarrollo profesional',
        icon: GraduationCap
    },
    4: {
        nombre: 'Créditos de Salud',
        descripcion: 'Cuida tu salud y la de tu familia',
        icon: Heart
    },
    5: {
        nombre: 'Créditos de Vivienda',
        descripcion: 'Cumple el sueño de tener tu propia vivienda',
        icon: Home
    },
    6: {
        nombre: 'Fomento y Emprendimiento Empresarial',
        descripcion: 'Impulsa tu negocio o proyecto empresarial',
        icon: Briefcase
    },
    7: {
        nombre: 'Otros Créditos',
        descripcion: 'Soluciones de crédito para diferentes necesidades',
        icon: MoreHorizontal
    },
    8: {
        nombre: 'Crédito de Mercadeo',
        descripcion: 'Financia tus actividades de mercadeo y ventas',
        icon: TrendingUp
    },
    9: {
        nombre: 'Recreación y Turismo',
        descripcion: 'Disfruta de tus momentos de ocio y viajes',
        icon: Plane
    }
};

const modalidadesAgrupadas = computed<Modalidad[]>(() => {
    const agrupadas: Record<number, LineaCredito[]> = {};

    if (Array.isArray(lineasCredito.value)) {
        lineasCredito.value.forEach((linea) => {
            if (linea && typeof linea.modxml4 === 'number') {
                const key = linea.modxml4;
                if (!agrupadas[key]) agrupadas[key] = [];
                agrupadas[key].push(linea);
            }
        });
    }

    return Object.entries(agrupadas)
        .map(([modxml4, lineas]) => {
            const mod = Number(modxml4);
            const info = modalidadesMap[mod] || {
                nombre: `Modalidad ${modxml4}`,
                descripcion: 'Líneas de crédito disponibles',
                icon: MoreHorizontal
            };

            return {
                modxml4: mod,
                ...info,
                lineas: lineas.sort((a, b) => a.detalle.localeCompare(b.detalle))
            };
        })
        .sort((a, b) => a.modxml4 - b.modxml4);
});

const fmtMoney = (value: unknown): string => {
    const n = typeof value === 'number' ? value : Number(value);
    const v = Number.isFinite(n) ? n : 0;
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(v);
};

const seleccionarLinea = (_linea: LineaCredito) => {
};

const simularLinea = (linea: LineaCredito) => {
    router.visit(`/web/simulador/${linea.tipcre}`);
};
</script>
