// Declaración de tipos para Vue
declare module "*.vue" {
  import type { DefineComponent } from "vue";
  const component: DefineComponent<{}, {}, any>;
  export default component;
}

// Declaración para Ziggy
declare module "../../vendor/tightenco/ziggy" {
  const Ziggy: any;
  export const ZiggyVue: any;
  export default Ziggy;
}

// Declaración para Inertia
declare module "@inertiajs/vue3" {
  export function createInertiaApp(config: any): void;
  export function resolvePageComponent(path: string, pages: any): any;
}

// Declaración para Laravel Vite Plugin
declare module "laravel-vite-plugin/inertia-helpers" {
  export function resolvePageComponent(path: string, pages: any): any;
}
