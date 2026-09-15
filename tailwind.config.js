import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class", // Habilitar modo oscuro basado en clase

    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.vue",
        "./Modules/**/resources/views/**/*.blade.php",
        "./Modules/**/resources/js/**/*.vue",
        "./Modules/**/resources/js/**/*.js",
    ],

    theme: {
        fontFamily: {
            sans: ["Satoshi", "sans-serif"],
        },
        extend: {
            colors: {
                // Paleta de marca Zync, tomada del logo (matiz 201°). Se declara
                // como `indigo` a proposito: es el nombre que ya usan todas las
                // clases de la interfaz, asi que el cambio de marca se aplica
                // desde aqui sin tocar los componentes.
                indigo: {
                    50: "#ECF3F7",
                    100: "#DCEAF1",
                    200: "#B1D9EF",
                    300: "#5AC2FA",
                    400: "#079DED",
                    500: "#067DBE",
                    600: "#056599",
                    700: "#045682",
                    800: "#03476B",
                    900: "#033D5D",
                    950: "#022437",
                },
            },
        },
    },

    plugins: [forms],
};
