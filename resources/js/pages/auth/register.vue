<template>
  <AuthLayout maxWidth="max-w-5xl lg:max-w-6xl xl:max-w-7xl">
    <div class="grid grid-cols-1 lg:grid-cols-6 gap-4">
      <div class="bg-white shadow-xl rounded-2xl p-6 lg:p-8 xl:p-10 lg:col-span-4 lg:col-start-2">
        <div class="space-y-6">
          <div class="text-center">
            <h1 class="text-2xl font-bold tracking-tight">Crear una cuenta</h1>
            <p class="text-sm text-muted-foreground mt-1">
              Completa los pasos para registrarte en el sistema.
            </p>
          </div>

          <!-- Indicadores de paso -->
          <div class="relative flex justify-between items-center px-4 py-2">
            <div class="absolute left-0 top-1/2 -z-10 h-0.5 w-full bg-border" />
            <div
              v-for="i in 3"
              :key="i"
              :class="cn(
                'relative flex h-8 w-8 items-center justify-center rounded-full border-2 text-sm font-bold transition-colors',
                pasoActual >= i ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background text-muted-foreground'
              )"
            >
              {{ i }}
            </div>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-6">
            <!-- Mensaje de error -->
            <div v-if="hasErrors" class="rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive flex items-center gap-2">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <div>
                <h3 class="font-medium">Error de registro</h3>
                <ul class="mt-1 space-y-1">
                  <li v-for="error in allErrors" :key="error">{{ error }}</li>
                </ul>
              </div>
            </div>

            <!-- Paso 1: Identificación -->
            <div v-if="pasoActual === 1" class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="tipo_documento">Tipo de documento</Label>
                  <select
                    id="tipo_documento"
                    v-model="form.tipo_documento"
                    required
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="form.processing"
                  >
                    <option value="">Seleccione...</option>
                    <option v-for="tipo in tiposDocumento" :key="tipo.value" :value="tipo.value">
                      {{ tipo.label }}
                    </option>
                  </select>
                </div>

                <div class="space-y-2">
                  <Label for="numero_documento">Número de documento</Label>
                  <Input
                    id="numero_documento"
                    v-model="form.numero_documento"
                    required
                    placeholder="1234567890"
                    :disabled="form.processing"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="nombres">Nombres</Label>
                  <Input
                    id="nombres"
                    v-model="form.nombres"
                    required
                    placeholder="Juan Carlos"
                    :disabled="form.processing"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="apellidos">Apellidos</Label>
                  <Input
                    id="apellidos"
                    v-model="form.apellidos"
                    required
                    placeholder="Pérez Gómez"
                    :disabled="form.processing"
                  />
                </div>
              </div>
            </div>

            <!-- Paso 2: Contacto -->
            <div v-if="pasoActual === 2" class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="email">Correo electrónico</Label>
                  <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    placeholder="usuario@ejemplo.com"
                    :disabled="form.processing"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="telefono">Teléfono celular</Label>
                  <Input
                    id="telefono"
                    v-model="form.telefono"
                    type="tel"
                    required
                    placeholder="3001234567"
                    :disabled="form.processing"
                  />
                </div>
              </div>
            </div>

            <!-- Paso 3: Seguridad -->
            <div v-if="pasoActual === 3" class="space-y-4">
              <div class="space-y-2">
                <Label for="username">Nombre de usuario</Label>
                <Input
                  id="username"
                  v-model="form.username"
                  required
                  placeholder="pepe123"
                  :disabled="form.processing"
                />
                <p class="text-[10px] text-muted-foreground">
                  Este será tu nombre de usuario para iniciar sesión
                </p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="password">Contraseña</Label>
                  <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    minlength="8"
                    placeholder="Mínimo 8 caracteres"
                    :disabled="form.processing"
                  />
                </div>

                <div class="space-y-2">
                  <Label for="confirmar_password">Confirmar contraseña</Label>
                  <Input
                    id="confirmar_password"
                    v-model="form.confirmar_password"
                    type="password"
                    required
                    minlength="8"
                    placeholder="Repita su contraseña"
                    :disabled="form.processing"
                  />
                </div>
              </div>
            </div>

            <!-- Botones de navegación -->
            <div class="pt-4 flex gap-3">
              <Button
                v-if="pasoActual > 1"
                type="button"
                variant="outline"
                class="flex-1"
                @click="pasoAnterior"
                :disabled="form.processing"
              >
                Anterior
              </Button>

              <Button
                v-if="pasoActual < 3"
                type="button"
                class="flex-1"
                :disabled="!canGoNext || form.processing"
                @click="pasoSiguiente"
              >
                Siguiente
              </Button>

              <Button
                v-else
                type="submit"
                class="flex-1"
                :disabled="!canSubmit || form.processing"
              >
                <svg v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                {{ form.processing ? 'Creando cuenta...' : 'Crear cuenta' }}
              </Button>
            </div>
          </form>

          <div class="text-center text-sm">
            <p class="text-muted-foreground">
              ¿Ya tienes una cuenta?
              <Link href="/login" class="font-medium text-primary underline underline-offset-4">
                Inicia sesión
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
    </div>
  </AuthLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';

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
