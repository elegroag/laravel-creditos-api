<template>
  <AuthLayout maxWidth="max-w-md lg:max-w-lg xl:max-w-xl">
    <div class="w-full min-w-[220px] max-w-lg">
      <div class="bg-white shadow-xl rounded-2xl">
        <div class="pb-4">
          <div class="p-6 lg:p-8 xl:p-10 space-y-6">
            <div class="text-center p-3">
              <h1 class="text-2xl font-bold tracking-tight">Iniciar sesión</h1>
              <p class="text-sm text-muted-foreground mt-1">
                Ingresa tus credenciales para acceder al sistema.
              </p>
            </div>

            <form @submit.prevent="handleLogin" class="space-y-4">
              <div class="space-y-2">
                <Label for="username">Usuario</Label>
                <Input
                  id="username"
                  v-model="username"
                  type="text"
                  placeholder="Tu nombre de usuario"
                  required
                  :disabled="loading"
                />
              </div>

              <div class="space-y-2">
                <Label for="password">Contraseña</Label>
                <Input
                  id="password"
                  v-model="password"
                  type="password"
                  placeholder="••••••••"
                  required
                  :disabled="loading"
                />
              </div>

              <Button
                type="submit"
                class="w-full"
                :disabled="loading || !isConnected"
              >
                <svg v-if="loading" class="mr-2 h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                {{ loading ? 'Ingresando...' : 'Ingresar' }}
              </Button>

              <div v-if="errorMsg" class="rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive flex items-center gap-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                {{ errorMsg }}
              </div>

              <!-- Mensaje de éxito -->
              <div v-if="props.status" class="rounded-lg border border-green-500/50 bg-green-50 p-3 text-sm text-green-800 flex items-center gap-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                {{ props.status }}
              </div>
            </form>

            <div class="text-center text-sm">
              <p class="text-muted-foreground">
                ¿No tienes cuenta?
                <Link href="/web/register" view-transition class="font-medium text-primary underline underline-offset-4">
                  Crear cuenta
                </Link>
              </p>
              <div class="mt-4">
                <Link href="/" class="text-xs text-muted-foreground hover:text-primary transition-colors">
                  Volver al inicio
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Estado de conexión -->
        <div
          :class="cn('border-t px-4 py-1 text-xs transition-colors', connectionStatusClass)"
        >
          <div class="flex items-center justify-center gap-1">
            <svg v-if="checkingConnection" class="h-3 w-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <svg v-else-if="isConnected" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <svg v-else class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <span class="text-xs">{{ connectionMessage }}</span>
          </div>
        </div>
      </div>
    </div>
  </AuthLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useLogin } from '@/composables/auth/useLogin';

interface Props {
  canResetPassword?: boolean;
  status?: string;
}

const props = defineProps<Props>();

const { username, password, loading, errorMsg, login, checkAuthAndRedirect } = useLogin();

// Estado de conexión (simulado)
const isConnected = ref(true);
const checkingConnection = ref(false);
const connectionMessage = ref('Conectado al servidor');
const connectionStatusClass = ref('text-green-600 bg-green-50');

const handleLogin = async () => {
  await login();
};

// Simular verificación de conexión
onMounted(() => {
  checkingConnection.value = true;
  connectionMessage.value = 'Verificando conexión...';

  setTimeout(() => {
    isConnected.value = true;
    checkingConnection.value = false;
    connectionMessage.value = 'Conectado al servidor';
    connectionStatusClass.value = 'text-green-600 bg-green-50';
  }, 1000);

  // Verificar si ya está autenticado
  checkAuthAndRedirect();
});
</script>
