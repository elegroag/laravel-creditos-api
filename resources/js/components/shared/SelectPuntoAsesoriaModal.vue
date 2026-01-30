<template>
    <!-- Modal overlay -->
    <div 
        v-if="isOpen" 
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        @click="closeModal"
    >
        <!-- Modal content -->
        <div 
            class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[80vh] overflow-hidden"
            @click.stop
        >
            <!-- Header -->
            <div class="border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">
                        Seleccionar Punto de Asesoría
                    </h2>
                    <button
                        @click="closeModal"
                        class="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-600 mt-1">
                    Se encontraron {{ puntosAsesoria?.length || 0 }} puntos de asesoría disponibles
                </p>
            </div>

            <!-- Body -->
            <div class="px-6 py-4 overflow-y-auto max-h-[60vh]">
                <div v-if="loading" class="flex justify-center py-8">
                    <svg class="h-6 w-6 animate-spin text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </div>

                <div v-else-if="!puntosAsesoria?.length" class="text-center py-8">
                    <svg class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-gray-600">No se encontraron puntos de asesoría disponibles</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="punto in puntosAsesoria"
                        :key="punto.numero"
                        class="border rounded-lg p-4 hover:border-blue-500 hover:bg-blue-50 cursor-pointer transition-all"
                        :class="{ 'border-blue-500 bg-blue-50': selectedPunto?.numero === punto.numero }"
                        @click="selectPunto(punto)"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="font-semibold text-gray-900">Punto {{ punto.numero }}</span>
                                    <span 
                                        class="px-2 py-1 text-xs rounded-full"
                                        :class="punto.estado === 'A' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                    >
                                        {{ punto.estado === 'A' ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <span class="text-gray-600">Oficina:</span>
                                        <span class="ml-2 text-gray-900">{{ punto.oficina_afiliacion }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Código:</span>
                                        <span class="ml-2 text-gray-900">{{ punto.code_oficina }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Usuario:</span>
                                        <span class="ml-2 text-gray-900">{{ punto.nombre_usuario }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Email:</span>
                                        <span class="ml-2 text-gray-900">{{ punto.email }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="ml-4">
                                <svg 
                                    v-if="selectedPunto?.numero === punto.numero"
                                    class="h-5 w-5 text-blue-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t px-6 py-4 bg-gray-50">
                <div class="flex justify-end gap-3">
                    <Button
                        variant="outline"
                        @click="closeModal"
                    >
                        Cancelar
                    </Button>
                    <Button
                        :disabled="!selectedPunto"
                        @click="confirmSelection"
                    >
                        Continuar
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import Button from '@/components/ui/Button.vue';

interface PuntoAsesoria {
    numero: number;
    oficina_afiliacion: string;
    code_oficina: string;
    nombre_usuario: string;
    email: string;
    estado: string;
}

interface Props {
    isOpen: boolean;
    puntosAsesoria?: PuntoAsesoria[];
    loading?: boolean;
}

interface Emits {
    (e: 'close'): void;
    (e: 'select', punto: PuntoAsesoria): void;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false
});

const emit = defineEmits<Emits>();

const selectedPunto = ref<PuntoAsesoria | null>(null);

// Reset selection when modal opens/closes
watch(() => props.isOpen, (newValue) => {
    if (!newValue) {
        selectedPunto.value = null;
    }
});

const closeModal = () => {
    emit('close');
};

const selectPunto = (punto: PuntoAsesoria) => {
    selectedPunto.value = punto;
};

const confirmSelection = () => {
    if (selectedPunto.value) {
        emit('select', selectedPunto.value);
    }
};
</script>
