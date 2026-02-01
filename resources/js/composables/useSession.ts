import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { router } from '@inertiajs/core'
import type { SessionData, SessionUser } from '@/types/auth'

const STORAGE_TOKEN_KEY = 'comfaca_credito_access_token'
const STORAGE_TOKEN_TYPE_KEY = 'comfaca_credito_token_type'

type InertiaPageProps = {
    auth?: {
        user: SessionUser | null
    }
}

export const getApiToken = (): string => {
    if (typeof window === 'undefined') return ''
    return sessionStorage.getItem(STORAGE_TOKEN_KEY) || ''
}

export const getApiTokenType = (): string => {
    if (typeof window === 'undefined') return 'bearer'
    return sessionStorage.getItem(STORAGE_TOKEN_TYPE_KEY) || 'bearer'
}

export const setApiToken = (token: string, tokenType: string = 'bearer'): void => {
    if (typeof window === 'undefined') return
    sessionStorage.setItem(STORAGE_TOKEN_KEY, token)
    sessionStorage.setItem(STORAGE_TOKEN_TYPE_KEY, tokenType)
}

export const clearApiToken = (): void => {
    if (typeof window === 'undefined') return
    sessionStorage.removeItem(STORAGE_TOKEN_KEY)
    sessionStorage.removeItem(STORAGE_TOKEN_TYPE_KEY)
}

export const useSession = () => {
    const page = usePage<InertiaPageProps>()

    const user = computed(() => page.props.auth?.user ?? null)

    const token = computed(() => getApiToken())
    const tokenType = computed(() => getApiTokenType())

    const session = computed<SessionData>(() => ({
        accessToken: token.value,
        tokenType: tokenType.value,
        user: user.value
    }))

    const isAuthenticated = computed(() => Boolean(user.value))

    const authHeader = computed(() => {
        if (!token.value) return {}
        return { Authorization: `${tokenType.value} ${token.value}` }
    })

    const setSession = async (data: SessionData) => {
        setApiToken(data.accessToken, data.tokenType)
    }

    const clearSession = async () => {
        clearApiToken()
    }

    const updateUserData = async (_userData: Partial<SessionUser>) => {
        router.reload({ only: ['auth'] })
    }

    const hasRole = computed(() => (role: string) => {
        return user.value?.roles.includes(role) || false
    })

    const hasPermission = computed(() => (permission: string) => {
        return user.value?.permissions.includes(permission) || false
    })

    const hasAnyRole = computed(() => (roles: string[]) => {
        if (!user.value?.roles) return false
        return roles.some(role => user.value!.roles.includes(role))
    })

    const hasAnyPermission = computed(() => (permissions: string[]) => {
        if (!user.value?.permissions) return false
        return permissions.some(permission => user.value!.permissions.includes(permission))
    })

    return {
        session,
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
    }
}
