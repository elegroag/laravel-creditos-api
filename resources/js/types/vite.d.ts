// Declaraciones para Vite y import.meta.glob
declare module "*.vue" {
    import type { DefineComponent } from "vue";
    const component: DefineComponent<{}, {}, any>;
    export default component;
}

// Declaración para import.meta.glob
interface ImportMetaGlob {
    <T = any>(pattern: string, options?: { eager: boolean }): Record<string, T>;
}

declare interface ImportMeta {
    glob: ImportMetaGlob;
    env: ImportMetaEnv;
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
    readonly VITE_API_URL: string;
    readonly VITE_PUSHER_APP_KEY: string;
    readonly VITE_PUSHER_APP_CLUSTER: string;
}

// Declaración para Ziggy
declare module "../../vendor/tightenco/ziggy" {
    const Ziggy: any;
    export const ZiggyVue: any;
    export default Ziggy;
}

// Declaración para Laravel Vite Plugin
declare module "laravel-vite-plugin/inertia-helpers" {
    export function resolvePageComponent(path: string, pages: any): any;
}

// Declaración para Vue
declare module "vue__shim" {
    export const createApp: any;
    export const h: any;
    export function defineComponent<T>(setup: () => T): T;
}

// Declaración para CSS
declare module "*.css" {
    const content: Record<string, string>;
    export default content;
}
