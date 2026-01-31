import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { useApi } from '@/composables/useApi'
import { AnyObject } from '@/types/global'
import { storage } from '~/composables/useStorage';

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
    password_confirmation: string
    general?: string
}

export function useRegistro(tipoDocumentos?: Record<string, string>) {
    const pasoActual = ref<number>(1)

    const form = useForm<FormData>({
        tipo_documento: '1', // Usar el primer valor del helper
        numero_documento: '',
        nombres: '',
        apellidos: '',
        telefono: '',
        email: '',
        username: '',
        password: '',
        password_confirmation: '',
    })

    const { postJson } = useApi()

    // Usar tipos de documento desde las props o fallback
    const tiposDocumento: TipoDocumento[] = tipoDocumentos
        ? Object.entries(tipoDocumentos).map(([value, label]) => ({ value, label }))
        : [
            { value: '1', label: 'CEDULA DE CIUDADANIA' },
            { value: '10', label: 'TARJETA DE MOVILIDAD FRONTERIZA' },
            { value: '11', label: 'CARNE DIPLOMATICO' },
            { value: '12', label: 'IDENTIFICACION DADA POR LA SECRETARIA DE EDUCACION' },
            { value: '13', label: 'VISA' },
            { value: '14', label: 'PERMISO PROTECCION TEMPORAL' },
            { value: '3', label: 'NIT' },
            { value: '4', label: 'CEDULA EXTRANJERIA' },
            { value: '6', label: 'PASAPORTE' },
            { value: '8', label: 'PERMISO ESPECIAL DE PERMANENCIA' },
            { value: '9', label: 'CERTIFICADO CABILDO' },
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
            form.password_confirmation &&
            form.password.length >= 8 &&
            form.password === form.password_confirmation
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

    // Generar username por defecto cuando cambian nombres y apellidos
    watch([() => form.nombres, () => form.apellidos], ([nombres, apellidos]) => {
        if (nombres && apellidos && !form.username) {
            const nombrePart = nombres.trim().replace(/\s/g, '').substring(0, 4).toLowerCase()
            const apellidoPart = apellidos.trim().replace(/\s/g, '').substring(0, 3).toLowerCase()
            form.username = `${nombrePart}${apellidoPart}`
        }
    })

    // UI-specific computed properties
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

    const handleSubmit = async (): Promise<void> => {
        if (pasoActual.value !== 3) return

        if (form.password !== form.password_confirmation) {
            form.setError('password_confirmation', 'Las contraseñas no coinciden')
            return
        }

        // Limpiar errores anteriores
        form.clearErrors()

        try {
            const payload = {
                email: form.email,
                username: form.username,
                numero_documento: form.numero_documento,
                password: form.password,
                tipo_documento: form.tipo_documento,
                nombres: form.nombres,
                apellidos: form.apellidos,
                telefono: form.telefono,
                password_confirmation: form.password_confirmation
            }

            // Usar useApi para enviar el formulario
            const response = await postJson<AnyObject>('auth/register', payload)

            form.reset('password', 'password_confirmation')

            if (response) {
                console.log('Registro exitoso:', response)
                // Resetear campos de contraseña

                const userData = {
                    username: response.user?.username || '',
                    email: payload.email,
                    tipo_documento: payload.tipo_documento,
                    numero_documento: payload.numero_documento,
                    nombres: payload.nombres,
                    apellidos: payload.apellidos,
                    roles: response.user?.roles || ['user']
                };
                await storage.setItem('comfaca_credito_user', userData);

                setTimeout(() => {

                    router.visit(
                        route('verify.show', {
                            coddoc: response?.data.coddoc,
                            documento: response?.data.documento
                        }),
                    );
                }, 1000);
            }

        } catch (error: unknown) {
            console.error('Error en el registro:', error)

            const err = error as {
                message?: string
                validationErrors?: Record<string, string[] | string>
            }

            // Manejar errores de validación
            if (err.validationErrors) {
                // Asignar errores de validación al formulario
                Object.entries(err.validationErrors).forEach(([field, messages]) => {
                    const fieldErrors = Array.isArray(messages) ? messages[0] : messages

                    if (Object.prototype.hasOwnProperty.call(form, field)) {
                        form.setError(field as keyof FormData, fieldErrors)
                    }
                })
            } else {
                // Manejar otros tipos de errores
                form.setError('general', err.message ?? 'Error al registrar usuario')
            }
        }
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
        canGoNext,
        canSubmit,
        hasErrors,
        allErrors,
        handleSubmit
    }
}
