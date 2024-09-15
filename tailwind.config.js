/** @type {import('tailwindcss').Config} */
const colors = {
    "base": "#000000",
    "warning": "#FCD452",
    "success": "#16A34A",
    "error": "#DC2626",
    "devlab": "#2A7DFD",
    "devlab-100": "#2A7DFD",
    "devgray-100": "#181818",
    "devgray-200": "#202020",
    "devgray-300": "#242424",
    "devgray-400": "#282828",
    "devgray-500": "#323232",
}
module.exports = {
    darkMode: "selector",
    content: [
        './storage/framework/views/*.php',
        "./resources/**/*.blade.php",
        "./app/**/*.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ["GTWalsheimPro", "sans-serif"],
            },
            colors
        },
    },
    plugins: [
        require("tailwindcss-scrollbar"),
        require("@tailwindcss/typography"),
        require("@tailwindcss/forms")
    ],
};
