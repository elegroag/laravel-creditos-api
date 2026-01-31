import type { route as routeFn } from 'ziggy-js';

// Tipos globales para la aplicación
declare global {
    interface Window {
        axios: any;
        Ziggy: any;
    }

    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        readonly VITE_PUSHER_APP_KEY: string;
        readonly VITE_PUSHER_APP_CLUSTER: string;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        glob: any;
    }

    const route: typeof routeFn;
}

// Tipos para Inertia.js
export interface PageProps {
    [key: string]: any;
}

export interface InertiaPage {
    component: string;
    props: PageProps;
    url: string;
    version: string;
}

// Tipos para componentes Vue
export interface ComponentProps {
    [key: string]: any;
}

// Tipos para Ziggy
export interface ZiggyConfig {
    url: string;
    port: number | null;
    defaults: Record<string, any>;
    routes: Record<string, any>;
}

// Tipos para configuración de la aplicación
export interface AppConfig {
    appName: string;
    apiBaseUrl: string;
    debug: boolean;
}


// global.d.ts
declare module "@inertiajs/core" {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: { user: { id: number; name: string } | null };
            appName: string;
        };
        flashDataType: {
            toast?: { type: "success" | "error"; message: string };
        };
        errorValueType: string[];
    }
}

export interface AnyObject {
    [key: string]: any;
}
