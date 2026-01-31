<template>
    <AuthLayout>
        <div class="bg-white shadow-xl rounded-2xl p-6 lg:p-8">
            <div class="space-y-6">
                <div class="text-center">
                    <h1 class="text-2xl font-bold tracking-tight">Verificar identidad</h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Ingresa el código de 6 dígitos enviado a tu correo.
                    </p>
                </div>

                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <div v-if="form.errors.codigo" class="rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
                        {{ form.errors.codigo }}
                    </div>

                    <div class="flex justify-center gap-2">
                        <input
                            v-for="i in 6"
                            :key="i"
                            :ref="(el) => setDigitRef(el, i - 1)"
                            v-model="digits[i - 1]"
                            inputmode="numeric"
                            maxlength="1"
                            class="flex h-12 w-12 rounded-md border border-input bg-background px-3 py-2 text-center text-lg ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.processing"
                            @input="onDigitInput(i - 1)"
                            @keydown.backspace="onBackspace(i - 1)"
                        />
                    </div>

                    <div class="flex gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            class="flex-1"
                            :disabled="form.processing"
                            @click="reset"
                        >
                            Limpiar
                        </Button>
                        <Button
                            type="submit"
                            class="flex-1"
                            :disabled="form.processing || !isComplete"
                        >
                            {{ form.processing ? 'Validando…' : 'Confirmar' }}
                        </Button>
                    </div>

                    <div class="text-center text-sm">
                        <Link href="/web/login" class="font-medium text-primary underline underline-offset-4">
                            Volver al inicio de sesión
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthLayout>
</template>

<script setup lang="ts">
import { computed, ref, nextTick } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';

interface Props {
    coddoc?: string | null
    documento?: string | null
}

const props = defineProps<Props>();

type VerifyForm = {
    codigo: string
    coddoc?: string | null
    documento?: string | null
}

const digits = ref<string[]>(['', '', '', '', '', '']);

const form = useForm<VerifyForm>({
    codigo: '',
    coddoc: props.coddoc ?? null,
    documento: props.documento ?? null,
});

const isComplete = computed((): boolean => digits.value.every((d) => d.length === 1));

const code = computed((): string => digits.value.join(''));

const inputRefs = ref<Array<HTMLInputElement | null>>([null, null, null, null, null, null]);

const setDigitRef = (el: Element | ComponentPublicInstance | null, index: number): void => {
    inputRefs.value[index] = el instanceof HTMLInputElement ? el : null;
};

const focusIndex = async (index: number): Promise<void> => {
    await nextTick();
    inputRefs.value[index]?.focus();
};

const normalizeDigit = (value: string): string => {
    const v = value.replace(/\D/g, '');
    return v.slice(0, 1);
};

const onDigitInput = async (index: number): Promise<void> => {
    digits.value[index] = normalizeDigit(digits.value[index] || '');

    if (digits.value[index] && index < 5) {
        await focusIndex(index + 1);
    }
};

const onBackspace = async (index: number): Promise<void> => {
    if (digits.value[index]) return;
    if (index === 0) return;
    await focusIndex(index - 1);
};

const reset = async (): Promise<void> => {
    digits.value = ['', '', '', '', '', ''];
    form.clearErrors();
    await focusIndex(0);
};

const handleSubmit = (): void => {
    form.codigo = code.value;

    form.post(route('verify.store'), {
        onSuccess: () => {
            reset();
        },
    });
};
</script>
