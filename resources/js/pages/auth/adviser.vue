<template>
    <AuthLayout>
        <div class="bg-white flex flex-col shadow-xl rounded-2xl">
            <div class="flex-1 flex items-center justify-center p-4">
                <div class="w-full max-w-sm space-y-6">
                    <div class="text-center mb-2 p-4">
                        <h1 class="text-2xl font-bold tracking-tight">Portal de Asesores</h1>
                        <p class="text-sm text-muted-foreground mt-1">Ingresa tus credenciales</p>
                    </div>

                    <form @submit.prevent="handleLogin" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="username" class="text-sm font-medium text-gray-700">Número de Usuario SISU</Label>
                            <Input
                                id="username"
                                v-model="username"
                                type="number"
                                placeholder="Tu nombre de usuario"
                                :disabled="loading"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="password" class="text-sm font-medium text-gray-700">Contraseña</Label>
                            <Input
                                id="password"
                                v-model="password"
                                type="password"
                                placeholder="•••••••••"
                                :disabled="loading"
                            />
                        </div>

                        <div v-if="errorMsg" class="text-xs text-red-600 text-center">{{ errorMsg }}</div>

                        <Button type="submit" class="w-full h-9 text-sm" :disabled="loading" variant="default">
                            {{ loading ? 'Iniciando...' : 'Iniciar sesión' }}
                        </Button>
                    </form>

                    <div class="text-center mt-4">
                        <a
                            href="https://www.comfaca.com"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-xs text-blue-600 hover:text-blue-500 transition-colors"
                        >
                            ¿Necesitas ayuda?
                        </a>
                    </div>
                </div>
            </div>

            <SelectPuntoAsesoriaModal
                :is-open="showPuntosModal"
                :puntos-asesoria="puntosAsesoria"
                :loading="loading"
                @close="cancelPuntoSelection"
                @select="selectPuntoAsesoria"
            />
        </div>
    </AuthLayout>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import SelectPuntoAsesoriaModal from '@/components/shared/SelectPuntoAsesoriaModal.vue';
import { useAdviser } from '@/composables/auth/useAdviser';

const {
    username,
    password,
    loading,
    errorMsg,
    login,
    checkAuthAndRedirect,
    showPuntosModal,
    puntosAsesoria,
    selectPuntoAsesoria,
    cancelPuntoSelection
} = useAdviser();

const handleLogin = async (): Promise<void> => {
    await login();
};

onMounted(async () => {
    await checkAuthAndRedirect();
});
</script>
