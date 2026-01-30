import { ref, computed } from 'vue'
import type { SessionData, SessionUser } from '@/types/auth'

const STORAGE_KEY_V1 = 'comfaca_credito_session'
const STORAGE_TOKEN_KEY = 'comfaca_credito_access_token'
const STORAGE_TOKEN_TYPE_KEY = 'comfaca_credito_token_type'
const STORAGE_USER_KEY = 'comfaca_credito_user'

const emptySession = (): SessionData => ({
    accessToken: '',
    tokenType: 'bearer',
    user: null
})

// Funciones de storage para localStorage
const storage = {
    getItem: async (key: string): Promise<string | null> => {
        if (typeof window === 'undefined') return null
        try {
            return localStorage.getItem(key)
        } catch {
            return null
        }
    },
    setItem: async (key: string, value: string): Promise<void> => {
        if (typeof window === 'undefined') return
        try {
            localStorage.setItem(key, value)
        } catch {
            // Silently fail
        }
    },
    removeItem: async (key: string): Promise<void> => {
        if (typeof window === 'undefined') return
        try {
            localStorage.removeItem(key)
        } catch {
            // Silently fail
        }
    }
}

export const useSession = () => {
    const session = ref<SessionData>(emptySession())
    const hydrated = ref(false)

    const hydrate = async () => {
        if (hydrated.value || typeof window === 'undefined') return
        hydrated.value = true

        try {
            const token = await storage.getItem(STORAGE_TOKEN_KEY)
            const tokenType = await storage.getItem(STORAGE_TOKEN_TYPE_KEY)
            const userRaw = await storage.getItem(STORAGE_USER_KEY)

            if (typeof token === 'string' && token) {
                session.value.accessToken = token
                if (typeof tokenType === 'string' && tokenType) {
                    session.value.tokenType = tokenType
                }

                if (typeof userRaw === 'string' && userRaw) {
                    const u = JSON.parse(userRaw)
                    if (u && typeof u === 'object') {
                        const username = typeof u.username === 'string' ? u.username : ''
                        const roles = Array.isArray(u.roles) ? u.roles.filter((r: any) => typeof r === 'string') : []
                        const permissions = Array.isArray(u.permissions) ? u.permissions.filter((p: any) => typeof p === 'string') : []
                        const email = typeof u.email === 'string' ? u.email : ''
                        const tipo_documento = typeof u.tipo_documento === 'string' ? u.tipo_documento : ''
                        const numero_documento = typeof u.numero_documento === 'string' ? u.numero_documento : ''
                        const nombres = typeof u.nombres === 'string' ? u.nombres : ''
                        const apellidos = typeof u.apellidos === 'string' ? u.apellidos : ''

                        session.value.user = {
                            id: u.id || 0,
                            username,
                            roles,
                            permissions,
                            email,
                            tipo_documento,
                            numero_documento,
                            nombres,
                            apellidos,
                            full_name: `${nombres} ${apellidos}`.trim(),
                            adviser_number: typeof u.adviser_number === 'string' ? u.adviser_number : undefined,
                            asesor: u.asesor && typeof u.asesor === 'object' ? {
                                full_name: typeof u.asesor.full_name === 'string' ? u.asesor.full_name : '',
                                email: typeof u.asesor.email === 'string' ? u.asesor.email : '',
                                celular: typeof u.asesor.celular === 'string' ? u.asesor.celular : '',
                                codigo_funcionario: typeof u.asesor.codigo_funcionario === 'string' ? u.asesor.codigo_funcionario : '',
                                estado: typeof u.asesor.estado === 'string' ? u.asesor.estado : '',
                                tipo_funcionario: typeof u.asesor.tipo_funcionario === 'string' ? u.asesor.tipo_funcionario : ''
                            } : undefined,
                            trabajador: u.trabajador && typeof u.trabajador === 'object' ? u.trabajador : null,
                            selected_punto: u.selected_punto && typeof u.selected_punto === 'object' ? u.selected_punto : null
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Error hydrating session:', error)
            clearSession()
        }
    }

    const setSession = async (data: SessionData) => {
        session.value = data

        if (typeof window === 'undefined') return

        try {
            await storage.setItem(STORAGE_TOKEN_KEY, data.accessToken)
            await storage.setItem(STORAGE_TOKEN_TYPE_KEY, data.tokenType)
            
            if (data.user) {
                await storage.setItem(STORAGE_USER_KEY, JSON.stringify(data.user))
            } else {
                await storage.removeItem(STORAGE_USER_KEY)
            }
        } catch (error) {
            console.error('Error saving session:', error)
        }
    }

    const clearSession = async () => {
        session.value = emptySession()

        if (typeof window === 'undefined') return

        try {
            await storage.removeItem(STORAGE_TOKEN_KEY)
            await storage.removeItem(STORAGE_TOKEN_TYPE_KEY)
            await storage.removeItem(STORAGE_USER_KEY)
        } catch (error) {
            console.error('Error clearing session:', error)
        }
    }

    const updateUserData = async (userData: Partial<SessionUser>) => {
        if (!session.value.user) return

        session.value.user = { ...session.value.user, ...userData }
        
        if (typeof window === 'undefined') return

        try {
            await storage.setItem(STORAGE_USER_KEY, JSON.stringify(session.value.user))
        } catch (error) {
            console.error('Error updating user data:', error)
        }
    }

    const isAuthenticated = computed(() => {
        return Boolean(session.value.accessToken && session.value.user)
    })

    const user = computed(() => session.value.user)
    const token = computed(() => session.value.accessToken)
    const tokenType = computed(() => session.value.tokenType)

    const authHeader = computed(() => {
        if (!session.value.accessToken) return {}
        return { Authorization: `${session.value.tokenType} ${session.value.accessToken}` }
    })

    const hasRole = computed(() => (role: string) => {
        return session.value.user?.roles.includes(role) || false
    })

    const hasPermission = computed(() => (permission: string) => {
        return session.value.user?.permissions.includes(permission) || false
    })

    const hasAnyRole = computed(() => (roles: string[]) => {
        if (!session.value.user?.roles) return false
        return roles.some(role => session.value.user!.roles.includes(role))
    })

    const hasAnyPermission = computed(() => (permissions: string[]) => {
        if (!session.value.user?.permissions) return false
        return permissions.some(permission => session.value.user!.permissions.includes(permission))
    })

    // Auto-hidratar en el cliente
    if (typeof window !== 'undefined') {
        hydrate()
    }

    return {
        session: computed(() => session.value),
        user,
        token,
        tokenType,
        isAuthenticated,
        authHeader,
        hasRole,
        hasPermission,
        hasAnyRole,
        hasAnyPermission,
        setSession,
        clearSession,
        updateUserData,
        hydrate
    }
}
