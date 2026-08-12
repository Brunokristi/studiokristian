import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            '@public': fileURLToPath(new URL('./resources/js/public', import.meta.url)),
            '@auth': fileURLToPath(new URL('./resources/js/auth', import.meta.url)),
            '@admin': fileURLToPath(new URL('./resources/js/backoffice/admin', import.meta.url)),
            '@client': fileURLToPath(new URL('./resources/js/backoffice/client', import.meta.url)),
            '@staff': fileURLToPath(new URL('./resources/js/backoffice/staff', import.meta.url)),
            '@shared': fileURLToPath(new URL('./resources/js/shared', import.meta.url)),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
});
