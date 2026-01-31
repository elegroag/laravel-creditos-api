import "@/assets/css/index.css";
import "./bootstrap";
import "./helpers/route";

import { createApp, h } from "vue";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";
import { createInertiaApp } from "@inertiajs/vue3";
// Configuración de la aplicación
const appName: string = import.meta.env.VITE_APP_NAME || "Comfaca Crédito";

// Creación de la aplicación Inertia con tipado mejorado
createInertiaApp({
    title: (title: string): string => `${title} - ${appName}`,
    resolve: (name: string) => {
        // Importación de componentes Vue (sin genéricos para evitar error)
        const pages = import.meta.glob("./pages/**/*.vue", {
            eager: true,
        });
        return pages[`./pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }: {
        el: HTMLElement;
        App: any;
        props: Record<string, any>;
        plugin: any;
    }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: "#4B5563",
        showSpinner: true,
    },
});
