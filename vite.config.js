import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/sass/app.scss",
                "resources/css/register.css",
                "resources/css/login.css",
                "resources/js/app.js",
                "resources/js/plaid-link.js",
            ],
            refresh: true,
        }),
    ],
});
