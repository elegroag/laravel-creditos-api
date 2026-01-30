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
        extend: {
            // Colores personalizados para Comfaca Crédito
            colors: {
                // Paleta principal: teal, verde, peach, crema
                "comfaca-teal": "#7CC5BB",
                "comfaca-green": "#79BD5F",
                "comfaca-peach": "#FEDEAE",
                "comfaca-cream": "#FAF4E8",

                // Colores de marca adicionales
                "comfaca-blue": "#3B82F6",
                "comfaca-indigo": "#6366F1",
                "comfaca-purple": "#8B5CF6",
                "comfaca-pink": "#EC4899",

                // Colores semánticos
                success: "#10B981",
                warning: "#F59E0B",
                error: "#EF4444",
                info: "#3B82F6",
            },

            // Familias de fuentes
            fontFamily: {
                sans: [
                    "Inter",
                    "system-ui",
                    "-apple-system",
                    "BlinkMacSystemFont",
                    "Segoe UI",
                    "sans-serif",
                ],
                mono: ["JetBrains Mono", "Consolas", "Monaco", "monospace"],
            },

            // Espaciado personalizado
            spacing: {
                18: "4.5rem",
                88: "22rem",
                128: "32rem",
            },

            // Bordes personalizados
            borderRadius: {
                "4xl": "2rem",
                "5xl": "2.5rem",
            },

            // Sombras personalizadas
            boxShadow: {
                soft: "0 2px 15px -3px rgba(0, 0, 0, 0.07), 0 10px 20px -2px rgba(0, 0, 0, 0.04)",
                medium: "0 4px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)",
                strong: "0 10px 40px -10px rgba(0, 0, 0, 0.15), 0 4px 25px -5px rgba(0, 0, 0, 0.1)",
            },

            // Animaciones personalizadas
            animation: {
                "fade-in": "fadeIn 0.5s ease-in-out",
                "slide-up": "slideUp 0.3s ease-out",
                "slide-down": "slideDown 0.3s ease-out",
                "scale-in": "scaleIn 0.2s ease-out",
                "bounce-soft": "bounceSoft 2s infinite",
            },

            // Keyframes para animaciones
            keyframes: {
                fadeIn: {
                    "0%": { opacity: "0" },
                    "100%": { opacity: "1" },
                },
                slideUp: {
                    "0%": { transform: "translateY(10px)", opacity: "0" },
                    "100%": { transform: "translateY(0)", opacity: "1" },
                },
                slideDown: {
                    "0%": { transform: "translateY(-10px)", opacity: "0" },
                    "100%": { transform: "translateY(0)", opacity: "1" },
                },
                scaleIn: {
                    "0%": { transform: "scale(0.95)", opacity: "0" },
                    "100%": { transform: "scale(1)", opacity: "1" },
                },
                bounceSoft: {
                    "0%, 100%": { transform: "translateY(-5%)" },
                    "50%": { transform: "translateY(0)" },
                },
            },

            // Transiciones personalizadas
            transitionProperty: {
                height: "height",
                spacing: "margin, padding",
            },

            // Tipografía personalizada
            fontSize: {
                "2xs": ["0.625rem", { lineHeight: "0.75rem" }],
                "6xl": ["3.75rem", { lineHeight: "1" }],
                "7xl": ["4.5rem", { lineHeight: "1" }],
                "8xl": ["6rem", { lineHeight: "1" }],
                "9xl": ["8rem", { lineHeight: "1" }],
            },

            // Anchos máximos personalizados
            maxWidth: {
                "8xl": "88rem",
                "9xl": "96rem",
            },

            // Alturas mínimas personalizadas
            minHeight: {
                128: "32rem",
            },

            // Z-index personalizado
            zIndex: {
                60: "60",
                70: "70",
                80: "80",
                90: "90",
                100: "100",
            },
        },
    },
    plugins: [
        daisyui,
        // Plugin para animaciones adicionales
        function ({ addUtilities, theme }) {
            const newUtilities = {
                ".text-shadow": {
                    textShadow: "0 2px 4px rgba(0,0,0,0.10)",
                },
                ".text-shadow-md": {
                    textShadow:
                        "0 4px 8px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08)",
                },
                ".text-shadow-lg": {
                    textShadow:
                        "0 15px 35px rgba(0,0,0,0.15), 0 5px 15px rgba(0,0,0,0.08)",
                },
                ".backdrop-blur-xs": {
                    backdropFilter: "blur(2px)",
                },
            };

            addUtilities(newUtilities);
        },
    ],
    daisyui: {
        themes: [
            {
                light: {
                    primary: "oklch(0.72 0.08 185)",
                    "primary-focus": "oklch(0.65 0.08 185)",
                    "primary-content": "oklch(1 0 0)",

                    secondary: "oklch(0.7 0.12 135)",
                    "secondary-focus": "oklch(0.63 0.12 135)",
                    "secondary-content": "oklch(1 0 0)",

                    accent: "oklch(0.92 0.05 75)",
                    "accent-focus": "oklch(0.83 0.05 75)",
                    "accent-content": "oklch(0.3 0.015 85.5)",

                    neutral: "oklch(0.15 0.015 185)",
                    "neutral-focus": "oklch(0.13 0.015 185)",
                    "neutral-content": "oklch(1 0 0)",

                    "base-100": "oklch(1 0 0)",
                    "base-200": "oklch(0.95 0.01 85.5)",
                    "base-300": "oklch(0.88 0.02 185)",
                    "base-content": "oklch(0.3 0.015 85.5)",

                    info: "oklch(0.72 0.08 185)",
                    success: "oklch(0.7 0.12 135)",
                    warning: "oklch(0.92 0.05 75)",
                    error: "oklch(0.577 0.245 27.325)",
                },
            },
            {
                dark: {
                    primary: "oklch(0.72 0.08 185)",
                    "primary-focus": "oklch(0.78 0.08 185)",
                    "primary-content": "oklch(0.15 0.015 185)",

                    secondary: "oklch(0.7 0.12 135)",
                    "secondary-focus": "oklch(0.76 0.12 135)",
                    "secondary-content": "oklch(0.15 0.015 185)",

                    accent: "oklch(0.85 0.06 75)",
                    "accent-focus": "oklch(0.91 0.06 75)",
                    "accent-content": "oklch(0.2 0.015 185)",

                    neutral: "oklch(0.15 0.015 185)",
                    "neutral-focus": "oklch(0.17 0.015 185)",
                    "neutral-content": "oklch(0.98 0.01 85.5)",

                    "base-100": "oklch(0.15 0.015 185)",
                    "base-200": "oklch(0.2 0.015 185)",
                    "base-300": "oklch(0.25 0.015 185)",
                    "base-content": "oklch(0.98 0.01 85.5)",

                    info: "oklch(0.72 0.08 185)",
                    success: "oklch(0.7 0.12 135)",
                    warning: "oklch(0.85 0.06 75)",
                    error: "oklch(0.396 0.141 25.723)",
                },
            },
        ],
        defaultTheme: "light",
        darkTheme: "dark",
        base: true,
        styled: true,
        utils: true,
        prefix: "",
        logs: true,
        themeRoot: ":root",
    },
};
