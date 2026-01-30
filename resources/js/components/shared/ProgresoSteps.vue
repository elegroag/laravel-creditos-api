<template>
    <div class="w-full py-4">
        <div class="relative flex items-center justify-between w-full max-w-3xl mx-auto">
            <!-- Línea de fondo -->
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-gray-200 -z-10"></div>
            
            <!-- Línea de progreso -->
            <div 
                class="absolute left-0 top-1/2 transform -translate-y-1/2 h-1 bg-primary transition-all duration-500 ease-in-out -z-10"
                :style="{ width: `${progressPercentage}%` }"
            ></div>

            <!-- Pasos -->
            <div 
                v-for="(step, index) in steps" 
                :key="step.key"
                class="flex flex-col items-center group cursor-default"
                :class="{ 'cursor-pointer': canNavigateTo(index) }"
                @click="canNavigateTo(index) && $emit('navigate', step.key)"
            >
                <div 
                    class="w-10 h-10 rounded-full flex items-center justify-center border-2 bg-white transition-colors duration-300"
                    :class="[
                        isCurrent(step.key) ? 'border-primary text-primary font-bold' : 
                        isCompleted(index) ? 'border-primary bg-primary text-white' : 'border-gray-300 text-gray-400'
                    ]"
                >
                    <svg v-if="isCompleted(index)" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span v-else>{{ index + 1 }}</span>
                </div>
                <span 
                    class="mt-2 text-xs font-medium transition-colors duration-300 absolute top-12 w-32 text-center"
                    :class="[
                        isCurrent(step.key) ? 'text-primary' : 
                        isCompleted(index) ? 'text-gray-700' : 'text-gray-400'
                    ]"
                >
                    {{ step.label }}
                </span>
            </div>
        </div>
        <!-- Espaciador para los textos absolutos -->
        <div class="h-8"></div>
    </div>
</template>

<script setup lang="ts">
export type StepKey = 'formulario' | 'documentos' | 'completado';

interface Step {
    key: StepKey;
    label: string;
}

const props = defineProps<{
    currentStep: StepKey;
}>();

const emit = defineEmits<{
    (e: 'navigate', step: StepKey): void;
}>();

const steps: Step[] = [
    { key: 'formulario', label: 'Solicitud' },
    { key: 'documentos', label: 'Documentos' },
    { key: 'completado', label: 'Enviar para Validación' }
];

const currentIndex = computed(() => steps.findIndex(s => s.key === props.currentStep));

const isCurrent = (key: StepKey) => key === props.currentStep;

const isCompleted = (index: number) => index < currentIndex.value;

const canNavigateTo = (index: number) => {
    // Solo permitir navegar hacia atrás a pasos completados, o al paso actual (sin efecto)
    // No permitimos saltar pasos hacia adelante arbitrariamente por defecto
    return index < currentIndex.value;
};

const progressPercentage = computed(() => {
    if (currentIndex.value === -1) return 0;
    return (currentIndex.value / (steps.length - 1)) * 100;
});
</script>
