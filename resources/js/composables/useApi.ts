import { computed, ref } from 'vue'
import type { SessionData } from '@/types/auth'

// Estado global de la sesión
const sessionData = ref<SessionData | null>(null)
const loading = ref(false)

export const useApi = () => {
    const baseUrl = computed(() => {
        // En producción, esto debería venir de una variable de entorno
        const raw = import.meta.env.VITE_API_URL || window.location.origin
        return raw.replace(/\/+$/, '')
    })

    const urlFor = (path: string) => {
        const p = path.startsWith('/') ? path : `/${path}`
        return `${baseUrl.value}${p}`
    }

    const getAuthHeader = () => {
        const token = sessionData.value?.accessToken
        return token ? { Authorization: `Bearer ${token}` } : {}
    }

    const postJson = async <T>(
        path: string,
        body: Record<string, any>,
        opts?: {
            auth?: boolean
            headers?: Record<string, string>
        }
    ): Promise<T> => {
        loading.value = true
        try {
            const headers: Record<string, string> = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(opts?.headers || {})
            }

            if (opts?.auth && sessionData.value?.accessToken) {
                headers['Authorization'] = `Bearer ${sessionData.value.accessToken}`
            }

            // Agregar CSRF token si existe
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken
            }

            const response = await fetch(urlFor(path), {
                method: 'POST',
                headers,
                body: JSON.stringify(body),
                credentials: 'same-origin'
            })

            if (!response.ok) {
                // Manejar errores de validación (422) y otros errores
                const errorData = await response.json().catch(() => ({}))

                // Si es un error de validación, lanzar con formato especial
                if (response.status === 422 && errorData.errors) {
                    const validationError = new Error('Validation failed')
                        ; (validationError as any).validationErrors = errorData.errors
                        ; (validationError as any).message = errorData.message || 'Validation failed'
                    throw validationError
                }

                throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`)
            }

            return await response.json()
        } catch (error) {
            // Propagar errores de validación y otros errores
            throw error
        } finally {
            loading.value = false
        }
    }

    const getJson = async <T>(
        path: string,
        opts?: {
            auth?: boolean
            headers?: Record<string, string>
        }
    ): Promise<T> => {
        loading.value = true
        try {
            const headers: Record<string, string> = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(opts?.headers || {})
            }

            if (opts?.auth && sessionData.value?.accessToken) {
                headers['Authorization'] = `Bearer ${sessionData.value.accessToken}`
            }

            const response = await fetch(urlFor(path), {
                method: 'GET',
                headers,
                credentials: 'same-origin'
            })

            if (!response.ok) {
                // Manejar errores de validación (422) y otros errores
                const errorData = await response.json().catch(() => ({}))

                // Si es un error de validación, lanzar con formato especial
                if (response.status === 422 && errorData.errors) {
                    const validationError = new Error('Validation failed')
                        ; (validationError as any).validationErrors = errorData.errors
                        ; (validationError as any).message = errorData.message || 'Validation failed'
                    throw validationError
                }

                throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`)
            }

            return await response.json()
        } catch (error) {
            // Propagar errores de validación y otros errores
            throw error
        } finally {
            loading.value = false
        }
    }

    const putJson = async <T>(
        path: string,
        body: Record<string, any>,
        opts?: {
            auth?: boolean
            headers?: Record<string, string>
        }
    ): Promise<T> => {
        loading.value = true
        try {
            const headers: Record<string, string> = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(opts?.headers || {})
            }

            if (opts?.auth && sessionData.value?.accessToken) {
                headers['Authorization'] = `Bearer ${sessionData.value.accessToken}`
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken
            }

            const response = await fetch(urlFor(path), {
                method: 'PUT',
                headers,
                body: JSON.stringify(body),
                credentials: 'same-origin'
            })

            if (!response.ok) {
                // Manejar errores de validación (422) y otros errores
                const errorData = await response.json().catch(() => ({}))

                // Si es un error de validación, lanzar con formato especial
                if (response.status === 422 && errorData.errors) {
                    const validationError = new Error('Validation failed')
                        ; (validationError as any).validationErrors = errorData.errors
                        ; (validationError as any).message = errorData.message || 'Validation failed'
                    throw validationError
                }

                throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`)
            }

            return await response.json()
        } catch (error) {
            // Propagar errores de validación y otros errores
            throw error
        } finally {
            loading.value = false
        }
    }

    const deleteJson = async <T>(
        path: string,
        opts?: {
            auth?: boolean
            headers?: Record<string, string>
        }
    ): Promise<T> => {
        loading.value = true
        try {
            const headers: Record<string, string> = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(opts?.headers || {})
            }

            if (opts?.auth && sessionData.value?.accessToken) {
                headers['Authorization'] = `Bearer ${sessionData.value.accessToken}`
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken
            }

            const response = await fetch(urlFor(path), {
                method: 'DELETE',
                headers,
                credentials: 'same-origin'
            })

            if (!response.ok) {
                // Manejar errores de validación (422) y otros errores
                const errorData = await response.json().catch(() => ({}))

                // Si es un error de validación, lanzar con formato especial
                if (response.status === 422 && errorData.errors) {
                    const validationError = new Error('Validation failed')
                        ; (validationError as any).validationErrors = errorData.errors
                        ; (validationError as any).message = errorData.message || 'Validation failed'
                    throw validationError
                }

                throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`)
            }

            return await response.json()
        } catch (error) {
            // Propagar errores de validación y otros errores
            throw error
        } finally {
            loading.value = false
        }
    }

    return {
        baseUrl,
        getJson,
        postJson,
        putJson,
        deleteJson,
        urlFor,
        loading: computed(() => loading.value),
        sessionData: computed(() => sessionData.value),
        setSessionData: (data: SessionData | null) => {
            sessionData.value = data
        },
        clearSessionData: () => {
            sessionData.value = null
        },
        getAuthHeader,
    }
}
