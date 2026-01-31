import { Ziggy } from '../ziggy';
import { route as createRoute } from 'ziggy-js';

// Crear función route global con la configuración de Ziggy
export const route = (name: string, params?: any, absolute?: boolean) => {
    return createRoute(name, params, absolute, Ziggy as any);
};

// Hacer disponible globalmente
if (typeof window !== 'undefined') {
    (window as any).route = route;
}
