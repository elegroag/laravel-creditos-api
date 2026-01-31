import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";
import { resolve } from "path";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.ts"],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            "@": resolve(__dirname, "resources/js"),
            "@pages": resolve(__dirname, "resources/js/pages"),
            "@components": resolve(__dirname, "resources/js/components"),
            "@composables": resolve(__dirname, "resources/js/composables"),
            "@layouts": resolve(__dirname, "resources/js/layouts"),
            "@types": resolve(__dirname, "resources/js/types"),
            "~": resolve(__dirname, "resources/js"),
            "~~": resolve(__dirname, "resources/"),
            "@@": resolve(__dirname, "resources/"),
            "ziggy-js": resolve(__dirname, "vendor/tightenco/ziggy"),
        },
    },
    build: {
        sourcemap: true,
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ["vue", "@inertiajs/vue3"],
                    ziggy: ["vendor/tightenco/ziggy/dist/index.esm.js"],
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
        hmr: {
            host: "localhost", // host que usa el navegador
            port: Number(process.env.VITE_PORT) || 5173, // puerto expuesto por Sail
        },
    },
    define: {
        __VUE_OPTIONS_API__: true,
        __VUE_PROD_DEVTOOLS__: false,
    },
    optimizeDeps: {
        include: ["vue", "@inertiajs/vue3"],
    },
});
