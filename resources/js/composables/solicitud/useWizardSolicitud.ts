import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSolicitudCreditoForm } from './useSolicitudCreditoForm';
import { useApi } from '@/composables/useApi';
import { useSession } from '@/composables/useSession';
import type { WizardStep } from '@/types/solicitud-credito';

export function useWizardSolicitud() {
    const { postJson } = useApi();
    const { user } = useSession();
    
    const {
        form,
        tieneConyuge,
        toggleConyuge,
        toggleEmpresaConyuge,
        autocalcularIngresos,
        addPropiedad,
        removePropiedad,
        addDeuda,
        removeDeuda,
        addReferencia,
        removeReferencia,
        formularioValido,
        prepararParaEnvio,
        resetearFormulario
    } = useSolicitudCreditoForm();

    // Estado del wizard
    const currentStep = ref(1);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const successModalOpen = ref(false);
    const solicitudId = ref<number | null>(null);

    // Configuración de pasos
    const steps: WizardStep[] = [
        { key: 'solicitud', title: 'Solicitud', short: 'Solicitud' },
        { key: 'solicitante', title: 'Datos del solicitante', short: 'Solicitante' },
        { key: 'conyuge', title: 'Datos del cónyuge (opcional)', short: 'Cónyuge' },
        { key: 'laboral', title: 'Información laboral', short: 'Laboral' },
        { key: 'ingresos', title: 'Ingresos y descuentos', short: 'Ingresos' },
        { key: 'economica', title: 'Información económica', short: 'Económica' },
        { key: 'propiedades', title: 'Propiedades', short: 'Propiedades' },
        { key: 'deudas', title: 'Deudas', short: 'Deudas' },
        { key: 'referencias', title: 'Referencias', short: 'Referencias' },
        { key: 'resumen', title: 'Resumen', short: 'Resumen' },
    ];

    // Computed properties
    const totalSteps = computed(() => steps.length);
    const isFirstStep = computed(() => currentStep.value === 1);
    const isLastStep = computed(() => currentStep.value === totalSteps.value);
    const progress = computed(() => (currentStep.value / totalSteps.value) * 100);

    const currentStepData = computed(() => steps[currentStep.value - 1]);
    const visitedSteps = ref<Set<number>>(new Set([1]));

    // Validaciones por paso
    const stepValidations = computed(() => ({
        1: () => form.value.credito.producto && form.value.credito.monto > 0,
        2: () => {
            const s = form.value.solicitante;
            return s.tipo_identificacion && 
                   s.numero_identificacion && 
                   s.nombres && 
                   s.apellidos && 
                   s.email && 
                   s.telefono;
        },
        3: () => true, // Opcional
        4: () => {
            const s = form.value.solicitante;
            return s.empresa.nombre && 
                   s.cargo && 
                   s.salario > 0;
        },
        5: () => form.value.solicitante.salario > 0,
        6: () => true, // Información económica opcional
        7: () => true, // Propiedades opcionales
        8: () => true, // Deudas opcionales
        9: () => true, // Referencias opcionales
        10: () => formularioValido.value,
    }));

    const currentStepValid = computed(() => {
        const validation = stepValidations.value[currentStep.value as keyof typeof stepValidations.value];
        return validation ? validation() : false;
    });

    // Métodos de navegación
    const nextStep = () => {
        if (!currentStepValid.value) {
            error.value = 'Por favor completa todos los campos requeridos antes de continuar';
            return;
        }

        if (currentStep.value < totalSteps.value) {
            visitedSteps.value.add(currentStep.value + 1);
            currentStep.value++;
            error.value = null;
        }
    };

    const prevStep = () => {
        if (currentStep.value > 1) {
            currentStep.value--;
            error.value = null;
        }
    };

    const goToStep = (step: number) => {
        if (step >= 1 && step <= totalSteps.value) {
            // Solo permitir ir a pasos visitados o el siguiente
            if (visitedSteps.value.has(step) || step === currentStep.value + 1) {
                currentStep.value = step;
                error.value = null;
            }
        }
    };

    // Métodos de guardado
    const saveProgress = async () => {
        try {
            loading.value = true;
            
            // Guardar progreso en localStorage o backend
            const progressData = {
                currentStep: currentStep.value,
                formData: form.value,
                visitedSteps: Array.from(visitedSteps.value),
            };

            await postJson('/api/solicitudes/save-progress', progressData, { auth: true });
        } catch (err: any) {
            console.error('Error guardando progreso:', err);
            // No mostrar error al usuario, es solo guardado de progreso
        } finally {
            loading.value = false;
        }
    };

    const loadProgress = async () => {
        try {
            const response = await postJson('/api/solicitudes/load-progress', {}, { auth: true });
            
            if (response.data) {
                currentStep.value = response.data.currentStep || 1;
                visitedSteps.value = new Set(response.data.visitedSteps || [1]);
                
                if (response.data.formData) {
                    Object.assign(form.value, response.data.formData);
                }
            }
        } catch (err: any) {
            console.error('Error cargando progreso:', err);
            // Si no hay progreso guardado, continuar con valores por defecto
        }
    };

    const submitSolicitud = async () => {
        if (!formularioValido.value) {
            error.value = 'Por favor completa todos los campos requeridos';
            return;
        }

        try {
            loading.value = true;
            error.value = null;

            const payload = prepararParaEnvio();
            
            // Agregar información del usuario
            payload.usuario_id = user.value?.id;

            const response = await postJson('/api/solicitudes', payload, { auth: true });

            if (response.solicitud) {
                solicitudId.value = response.solicitud.id;
                successModalOpen.value = true;
                
                // Limpiar formulario después del éxito
                setTimeout(() => {
                    resetearFormulario();
                    currentStep.value = 1;
                    visitedSteps.value = new Set([1]);
                }, 2000);
            }

            return response;
        } catch (err: any) {
            console.error('Error enviando solicitud:', err);
            error.value = err.message || 'Error al enviar la solicitud';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    // Utilidades
    const getStepTitle = (step: number) => {
        return steps[step - 1]?.title || '';
    };

    const getStepShort = (step: number) => {
        return steps[step - 1]?.short || '';
    };

    const isStepVisited = (step: number) => {
        return visitedSteps.value.has(step);
    };

    const isStepCompleted = (step: number) => {
        if (step === 1) return form.value.credito.producto && form.value.credito.monto > 0;
        if (step === 2) {
            const s = form.value.solicitante;
            return s.tipo_identificacion && s.numero_identificacion && s.nombres && s.apellidos;
        }
        if (step === 4) {
            const s = form.value.solicitante;
            return s.empresa.nombre && s.cargo && s.salario > 0;
        }
        return isStepVisited(step);
    };

    const resetWizard = () => {
        currentStep.value = 1;
        visitedSteps.value = new Set([1]);
        loading.value = false;
        error.value = null;
        successModalOpen.value = false;
        solicitudId.value = null;
        resetearFormulario();
    };

    // Auto-guardado cada 30 segundos
    let autoSaveInterval: NodeJS.Timeout | null = null;
    
    const startAutoSave = () => {
        if (autoSaveInterval) clearInterval(autoSaveInterval);
        
        autoSaveInterval = setInterval(() => {
            if (formularioValido.value) {
                saveProgress();
            }
        }, 30000);
    };

    const stopAutoSave = () => {
        if (autoSaveInterval) {
            clearInterval(autoSaveInterval);
            autoSaveInterval = null;
        }
    };

    // Inicialización
    const initialize = async () => {
        await loadProgress();
        startAutoSave();
    };

    // Cleanup
    const cleanup = () => {
        stopAutoSave();
    };

    return {
        // Estado
        currentStep,
        totalSteps,
        steps,
        loading,
        error,
        successModalOpen,
        solicitudId,
        visitedSteps,

        // Computed
        isFirstStep,
        isLastStep,
        progress,
        currentStepData,
        currentStepValid,

        // Métodos de navegación
        nextStep,
        prevStep,
        goToStep,

        // Métodos de formulario (delegados)
        tieneConyuge,
        toggleConyuge,
        toggleEmpresaConyuge,
        autocalcularIngresos,
        addPropiedad,
        removePropiedad,
        addDeuda,
        removeDeuda,
        addReferencia,
        removeReferencia,

        // Métodos de guardado
        saveProgress,
        loadProgress,
        submitSolicitud,

        // Utilidades
        getStepTitle,
        getStepShort,
        isStepVisited,
        isStepCompleted,
        resetWizard,

        // Ciclo de vida
        initialize,
        cleanup,
    };
}
