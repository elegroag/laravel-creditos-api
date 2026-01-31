<template>
    <DashboardLayout>
        <div class="mx-auto max-w-4xl p-4 sm:p-8">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-zinc-900">Mi perfil</h1>
                    <p class="mt-1 text-sm text-zinc-600">Actualiza tu información personal.</p>
                </div>

                <div class="flex items-center gap-3">
                    <span v-if="loading" class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700">
                        Cargando…
                    </span>
                    <span v-else class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700">
                        Perfil
                    </span>
                </div>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-6">
                <div v-if="success" class="rounded-md bg-green-50 p-4">
                    <p class="text-sm font-medium text-green-800">{{ success }}</p>
                </div>

                <div v-if="error" class="rounded-md bg-red-50 p-4">
                    <p class="text-sm font-medium text-red-800">{{ error }}</p>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm" :class="loading ? 'opacity-60 pointer-events-none' : ''">
                    <div class="mb-6">
                        <h2 class="text-lg font-medium text-zinc-900">Información personal</h2>
                        <p class="mt-1 text-sm text-zinc-600">Algunos campos no se pueden editar.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <Label for="username">Usuario</Label>
                            <Input id="username" v-model="perfil.username" disabled />
                        </div>

                        <div class="sm:col-span-3">
                            <Label for="email">Correo electrónico</Label>
                            <Input id="email" v-model="perfil.email" type="email" :disabled="guardando" />
                        </div>

                        <div class="sm:col-span-3">
                            <Label for="nombres">Nombres</Label>
                            <Input id="nombres" v-model="perfil.nombres" :disabled="guardando" />
                        </div>

                        <div class="sm:col-span-3">
                            <Label for="apellidos">Apellidos</Label>
                            <Input id="apellidos" v-model="perfil.apellidos" :disabled="guardando" />
                        </div>

                        <div class="sm:col-span-6">
                            <Label for="phone">Teléfono</Label>
                            <Input id="phone" v-model="perfil.phone" :disabled="guardando" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-5">
                    <Button type="button" variant="outline" @click="recargarPerfil" :disabled="guardando || loading">
                        Recargar
                    </Button>
                    <Button type="submit" :disabled="guardando || loading">
                        Guardar cambios
                    </Button>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { usePerfil } from '@/composables/perfil/usePerfil';

const {
    perfil,
    loading,
    guardando,
    error,
    success,
    cargarPerfil,
    actualizarPerfil,
    recargarPerfil
} = usePerfil();

const handleSubmit = async (): Promise<void> => {
    await actualizarPerfil();
};

onMounted(async () => {
    await cargarPerfil();
});
</script>
