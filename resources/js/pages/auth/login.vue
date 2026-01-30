<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100">
          <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Iniciar sesión
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          Ingresa tus credenciales para acceder al sistema
        </p>
      </div>

      <form @submit.prevent="login" class="mt-8 space-y-6">
        <!-- Mensaje de éxito -->
        <div v-if="status" class="rounded-md bg-green-50 p-4">
          <div class="flex">
            <div class="shrink-0">
              <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-green-800">{{ status }}</p>
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700">
              Nombre de usuario
            </label>
            <div class="mt-1">
              <input
                id="username"
                v-model="form.username"
                name="username"
                type="text"
                required
                class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                placeholder="Tu nombre de usuario"
                :disabled="form.processing"
              />
            </div>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">
              Contraseña
            </label>
            <div class="mt-1">
              <input
                id="password"
                v-model="form.password"
                name="password"
                type="password"
                required
                class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                placeholder="••••••••"
                :disabled="form.processing"
              />
            </div>
          </div>
        </div>

        <div>
          <button
            type="submit"
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="form.processing"
          >
            <svg
              v-if="form.processing"
              class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              ></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>
            {{ form.processing ? 'Ingresando...' : 'Ingresar' }}
          </button>
        </div>

        <!-- Mensajes de error -->
        <div v-if="Object.keys(form.errors).length > 0 || errorMessage" class="rounded-md bg-red-50 p-4">
          <div class="flex">
            <div class="shrink-0">
              <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800">
                Error de autenticación
              </h3>
              <div class="mt-2 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                  <li v-if="form.errors.username">{{ form.errors.username }}</li>
                  <li v-if="form.errors.password">{{ form.errors.password }}</li>
                  <li v-if="errorMessage">{{ errorMessage }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </form>

      <div class="text-center">
        <p class="text-sm text-gray-600">
          ¿No tienes cuenta?
          <Link href="/register" class="font-medium text-blue-600 hover:text-blue-500">
            Crear cuenta
          </Link>
        </p>
        <div class="mt-4">
          <Link href="/" class="text-xs text-gray-500 hover:text-gray-700 transition-colors">
            Volver al inicio
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'

interface Props {
  canResetPassword?: boolean
  status?: string
}

defineProps<Props>()

const form = useForm({
  username: '',
  password: '',
})

const errorMessage = ref('')

const login = () => {
  errorMessage.value = ''

  form.post('/login', {
    onSuccess: () => {
      // Redirección automática por Inertia
    },
    onError: (errors) => {
      // Manejar errores de validación
      if (errors.username || errors.password) {
        errorMessage.value = 'Credenciales incorrectas'
      }
    },
    onFinish: () => {
      form.reset('password')
    },
  })
}
</script>
