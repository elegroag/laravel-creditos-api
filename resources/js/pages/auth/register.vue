<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl w-full space-y-8">
      <div>
        <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100">
          <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
          </svg>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Crear una cuenta
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          Completa los pasos para registrarte en el sistema
        </p>
      </div>

      <!-- Indicadores de paso -->
      <div class="relative flex justify-between items-center px-4 py-2 max-w-md mx-auto">
        <div class="absolute left-0 top-1/2 -z-10 h-0.5 w-full bg-gray-300"></div>
        <div
          v-for="i in 3"
          :key="i"
          :class="[
            'relative flex h-8 w-8 items-center justify-center rounded-full border-2 text-sm font-bold transition-colors',
            pasoActual >= i
              ? 'border-blue-600 bg-blue-600 text-white'
              : 'border-gray-300 bg-white text-gray-500'
          ]"
        >
          {{ i }}
        </div>
      </div>

      <form @submit.prevent="handleSubmit" class="mt-8 space-y-6">
        <!-- Mensaje de error -->
        <div v-if="hasErrors" class="rounded-md bg-red-50 p-4">
          <div class="flex">
            <div class="shrink-0">
              <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800">Error de registro</h3>
              <div class="mt-2 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                  <li v-for="error in allErrors" :key="error">{{ error }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Paso 1: Identificación -->
        <div v-if="pasoActual === 1" class="space-y-4">
          <h3 class="text-lg font-medium text-gray-900">Información personal</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="tipo_documento" class="block text-sm font-medium text-gray-700">
                Tipo de documento
              </label>
              <select
                id="tipo_documento"
                v-model="form.tipo_documento"
                required
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                :disabled="form.processing"
              >
                <option value="">Seleccione...</option>
                <option v-for="tipo in tiposDocumento" :key="tipo.value" :value="tipo.value">
                  {{ tipo.label }}
                </option>
              </select>
            </div>

            <div>
              <label for="numero_documento" class="block text-sm font-medium text-gray-700">
                Número de documento
              </label>
              <input
                id="numero_documento"
                v-model="form.numero_documento"
                type="text"
                required
                placeholder="1234567890"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                :disabled="form.processing"
              />
            </div>

            <div>
              <label for="nombres" class="block text-sm font-medium text-gray-700">
                Nombres
              </label>
              <input
                id="nombres"
                v-model="form.nombres"
                type="text"
                required
                placeholder="Juan Carlos"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                :disabled="form.processing"
              />
            </div>

            <div>
              <label for="apellidos" class="block text-sm font-medium text-gray-700">
                Apellidos
              </label>
              <input
                id="apellidos"
                v-model="form.apellidos"
                type="text"
                required
                placeholder="Pérez Gómez"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                :disabled="form.processing"
              />
            </div>
          </div>
        </div>

        <!-- Paso 2: Contacto -->
        <div v-if="pasoActual === 2" class="space-y-4">
          <h3 class="text-lg font-medium text-gray-900">Información de contacto</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700">
                Correo electrónico
              </label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                required
                placeholder="usuario@ejemplo.com"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                :disabled="form.processing"
              />
            </div>

            <div>
              <label for="telefono" class="block text-sm font-medium text-gray-700">
                Teléfono celular
              </label>
              <input
                id="telefono"
                v-model="form.telefono"
                type="tel"
                required
                placeholder="3001234567"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                :disabled="form.processing"
              />
            </div>
          </div>
        </div>

        <!-- Paso 3: Seguridad -->
        <div v-if="pasoActual === 3" class="space-y-4">
          <h3 class="text-lg font-medium text-gray-900">Configuración de acceso</h3>
          <div class="space-y-4">
            <div>
              <label for="username" class="block text-sm font-medium text-gray-700">
                Nombre de usuario
              </label>
              <input
                id="username"
                v-model="form.username"
                type="text"
                required
                placeholder="pepe123"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                :disabled="form.processing"
              />
              <p class="mt-1 text-xs text-gray-500">
                Este será tu nombre de usuario para iniciar sesión
              </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label for="password" class="block text-sm font-medium text-gray-700">
                  Contraseña
                </label>
                <input
                  id="password"
                  v-model="form.password"
                  type="password"
                  required
                  minlength="8"
                  placeholder="Mínimo 8 caracteres"
                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  :disabled="form.processing"
                />
              </div>

              <div>
                <label for="confirmar_password" class="block text-sm font-medium text-gray-700">
                  Confirmar contraseña
                </label>
                <input
                  id="confirmar_password"
                  v-model="form.confirmar_password"
                  type="password"
                  required
                  minlength="8"
                  placeholder="Repita su contraseña"
                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                  :disabled="form.processing"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Botones de navegación -->
        <div class="pt-4 flex gap-3">
          <button
            v-if="pasoActual > 1"
            type="button"
            class="flex-1 px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            @click="pasoAnterior"
            :disabled="form.processing"
          >
            Anterior
          </button>

          <button
            v-if="pasoActual < 3"
            type="button"
            class="flex-1 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="!canGoNext || form.processing"
            @click="pasoSiguiente"
          >
            Siguiente
          </button>

          <button
            v-else
            type="submit"
            class="flex-1 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="!canSubmit || form.processing"
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
            {{ form.processing ? 'Creando cuenta...' : 'Crear cuenta' }}
          </button>
        </div>
      </form>

      <div class="text-center">
        <p class="text-sm text-gray-600">
          ¿Ya tienes una cuenta?
          <Link href="/login" class="font-medium text-blue-600 hover:text-blue-500">
            Inicia sesión
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
import { ref, computed, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'

interface Props {
  username?: string
  redirect?: string
}

interface TipoDocumento {
  value: string
  label: string
}

interface FormData {
  tipo_documento: string
  numero_documento: string
  nombres: string
  apellidos: string
  telefono: string
  email: string
  username: string
  password: string
  confirmar_password: string
}

const props = defineProps<Props>()

const pasoActual = ref<number>(1)

const form = useForm<FormData>({
  tipo_documento: 'CC',
  numero_documento: '',
  nombres: '',
  apellidos: '',
  telefono: '',
  email: '',
  username: '',
  password: '',
  confirmar_password: '',
})

const tiposDocumento: TipoDocumento[] = [
  { value: 'CC', label: 'Cédula de Ciudadanía' },
  { value: 'CE', label: 'Cédula de Extranjería' },
  { value: 'PASAPORTE', label: 'Pasaporte' },
  { value: 'NIT', label: 'NIT' }
]

// Validaciones para cada paso
const validarPaso1 = computed((): boolean => {
  return Boolean(
    form.tipo_documento &&
    form.numero_documento &&
    form.nombres &&
    form.apellidos
  )
})

const validarPaso2 = computed((): boolean => {
  return Boolean(
    form.email &&
    form.telefono
  )
})

const validarPaso3 = computed((): boolean => {
  return Boolean(
    form.username &&
    form.password &&
    form.confirmar_password &&
    form.password.length >= 8 &&
    form.password === form.confirmar_password
  )
})

const canGoNext = computed((): boolean => {
  if (pasoActual.value === 1) return validarPaso1.value
  if (pasoActual.value === 2) return validarPaso2.value
  return false
})

const canSubmit = computed((): boolean => {
  return validarPaso3.value
})

const hasErrors = computed((): boolean => {
  return Object.keys(form.errors).length > 0
})

const allErrors = computed((): string[] => {
  const errors: string[] = []

  if (form.errors.email) errors.push(form.errors.email as string)
  if (form.errors.username) errors.push(form.errors.username as string)
  if (form.errors.numero_documento) errors.push(form.errors.numero_documento as string)
  if (form.errors.password) errors.push(form.errors.password as string)
  if (form.errors.tipo_documento) errors.push(form.errors.tipo_documento as string)
  if (form.errors.nombres) errors.push(form.errors.nombres as string)
  if (form.errors.apellidos) errors.push(form.errors.apellidos as string)
  if (form.errors.telefono) errors.push(form.errors.telefono as string)

  return errors
})

// Generar username por defecto
watch([() => form.nombres, () => form.apellidos], ([nombres, apellidos]) => {
  if (nombres && apellidos && !form.username) {
    const nombrePart = nombres.trim().replace(/\s/g, '').substring(0, 4).toLowerCase()
    const apellidoPart = apellidos.trim().replace(/\s/g, '').substring(0, 3).toLowerCase()
    form.username = `${nombrePart}${apellidoPart}`
  }
})

const pasoSiguiente = (): void => {
  if (pasoActual.value < 3) {
    pasoActual.value++
  }
}

const pasoAnterior = (): void => {
  if (pasoActual.value > 1) {
    pasoActual.value--
  }
}

const handleSubmit = (): void => {
  if (pasoActual.value === 3) {
    if (form.password !== form.confirmar_password) {
      form.setError('confirmar_password', 'Las contraseñas no coinciden')
      return
    }

    // Eliminar confirmar_password antes de enviar
    const { confirmar_password, ...datosRegistro } = form

    form.post('/register', {
      onSuccess: () => {
        // Redirección automática por Inertia
      },
      onError: () => {
        // Manejar errores de validación - Inertia maneja automáticamente
      },
      onFinish: () => {
        form.reset('password', 'confirmar_password')
      },
    })
  }
}
</script>
