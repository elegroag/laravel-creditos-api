import axios from "axios";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";

// Configuración global de axios
window.axios = axios;

// Headers por defecto para todas las peticiones
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// Token CSRF para peticiones POST/PUT/DELETE
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common["X-CSRF-TOKEN"] =
        token.getAttribute("content");
} else {
    console.error(
        "CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token",
    );
}

// Configuración de Ziggy para rutas de Laravel
// Ziggy se configura automáticamente desde Laravel
window.ZiggyVue = ZiggyVue;

// Interceptor para manejar errores de autenticación
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // Redirigir a login si el token expira
            window.location.href = "/login";
        }
        return Promise.reject(error);
    },
);
