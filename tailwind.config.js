/** @type {import('tailwindcss').Config} */
import daisyui from "daisyui";

export default {
    content: [
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.vue",
        "./resources/js/**/*.js",
        "./resources/js/**/*.ts",
        "./resources/js/layouts/**/*.vue",
        "./resources/js/components/**/*.vue",
        "./resources/js/pages/**/*.vue",
        "./resources/js/composables/**/*.js",
        "./resources/js/composables/**/*.ts",
        "./resources/js/types/**/*.ts",
        "./resources/js/utils/**/*.ts",
    ],
    theme: {
        extend: {},
    },
    plugins: [daisyui],
    daisyui: {
        themes: ["dark", "light"],
        defaultTheme: "light",
    },
};
