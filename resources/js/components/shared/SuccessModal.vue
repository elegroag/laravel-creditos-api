<template>
    <Teleport to="body">
        <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-background/80 backdrop-blur-sm" @click="$emit('close')" />
            <Card class="relative w-full max-w-md shadow-2xl border-primary/20 animate-in zoom-in-95 duration-200" @click.stop>
                <CardHeader class="text-center pb-2">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-secondary/20 text-secondary">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <CardTitle class="text-xl font-bold text-foreground">Solicitud creada con éxito</CardTitle>
                    <CardDescription>
                        Tu solicitud fue enviada y quedó en estado
                        <span class="font-bold text-secondary-foreground bg-secondary/30 px-1.5 py-0.5 rounded">Postulado</span>.
                    </CardDescription>
                </CardHeader>
                
                <CardContent class="space-y-4">
                    <div v-if="solicitudId" class="rounded-lg bg-muted p-3 space-y-1">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">ID de Solicitud</div>
                        <div class="font-mono text-sm break-all text-foreground">{{ solicitudId }}</div>
                    </div>
                    
                    <div v-if="filename" class="rounded-lg bg-muted p-3 space-y-1">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Archivo XML</div>
                        <div class="text-sm text-foreground break-all">{{ filename }}</div>
                    </div>

                    <div class="grid grid-cols-1 gap-2 pt-2">
                        <Button
                            variant="secondary"
                            class="w-full"
                            @click="$emit('viewSolicitudes')"
                        >
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            Ver mis solicitudes
                        </Button>
                        
                        <Button
                            v-if="solicitudId"
                            class="w-full bg-primary hover:bg-primary/90"
                            @click="$emit('goToDocumentos')"
                        >
                            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Continuar a Carga de Documentos
                        </Button>
                        
                        <Button
                            variant="ghost"
                            class="w-full text-muted-foreground hover:text-foreground"
                            @click="$emit('close')"
                        >
                            Cerrar
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import Card from '@/components/ui/Card.vue';
import CardHeader from '@/components/ui/CardHeader.vue';
import CardTitle from '@/components/ui/CardTitle.vue';
import CardDescription from '@/components/ui/CardDescription.vue';
import CardContent from '@/components/ui/CardContent.vue';
import Button from '@/components/ui/Button.vue';

interface Props {
    isOpen: boolean;
    solicitudId?: string;
    filename?: string;
}

defineProps<Props>();

defineEmits<{
    close: [];
    viewSolicitudes: [];
    goToDocumentos: [];
}>();
</script>
