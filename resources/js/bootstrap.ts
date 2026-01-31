import axios, { AxiosInstance, AxiosResponse, AxiosError } from "axios";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";
import { ErrorResponse } from "./types/global";

// Configuración global de axios con tipado fuerte
const axiosInstance: AxiosInstance = axios.create({
    timeout: 10000, // 10 segundos timeout
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Headers por defecto para todas las peticiones
axiosInstance.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// Token CSRF para peticiones POST/PUT/DELETE con validación de tipo
const csrfTokenElement: HTMLMetaElement | null = document.head.querySelector('meta[name="csrf-token"]');
if (csrfTokenElement) {
    const csrfToken: string | null = csrfTokenElement.getAttribute("content");
    if (csrfToken) {
        axiosInstance.defaults.headers.common["X-CSRF-TOKEN"] = csrfToken;
    } else {
        console.error("CSRF token content is empty");
    }
} else {
    console.error(
        "CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token",
    );
}

window.Ziggy = ZiggyVue;
window.axios = axiosInstance;

// Interceptor para manejar errores de autenticación con tipado fuerte
axiosInstance.interceptors.response.use(
    (response: AxiosResponse): AxiosResponse => {
        return response;
    },
    (error: AxiosError<ErrorResponse>): Promise<AxiosError<ErrorResponse>> => {
        // Manejo específico de errores 401 (no autorizado)
        if (error.response?.status === 401) {
            console.warn("Sesión expirada, redirigiendo al login");
            window.location.href = "/login";
        }

        // Manejo de errores 419 (token CSRF inválido)
        if (error.response?.status === 419) {
            console.error("Token CSRF inválido o expirado");
            window.location.reload();
        }

        // Manejo de errores 422 (validación)
        if (error.response?.status === 422) {
            const validationErrors = error.response.data?.errors;
            if (validationErrors) {
                console.error("Errores de validación:", validationErrors);
            }
        }

        // Manejo de errores 500 (error del servidor)
        if (error.response?.status === 500) {
            console.error("Error interno del servidor");
        }

        // Manejo de errores de red
        if (!error.response) {
            console.error("Error de conexión:", error.message);
        }

        return Promise.reject(error);
    },
);

// Exportar instancia tipada para uso en otros módulos
export default axiosInstance;

