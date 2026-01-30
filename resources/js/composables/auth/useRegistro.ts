import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

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

export function useRegistro() {
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

  const registrar = (): void => {
    if (pasoActual.value !== 3) return

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

  return {
    form,
    loading: computed(() => form.processing),
    error: computed(() => {
      const errors = Object.keys(form.errors)
      return errors.length > 0 ? Object.values(form.errors)[0] as string : null
    }),
    pasoActual,
    tiposDocumento,
    validarPaso1,
    validarPaso2,
    validarPaso3,
    pasoSiguiente,
    pasoAnterior,
    registrar
  }
}
