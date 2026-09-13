import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/admin.js',
                'resources/js/public.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // 0.0.0.0 agar dev server terjangkau dari luar container Docker.
        // HMR tetap menunjuk localhost: alamat itulah yang dipakai browser,
        // bukan alamat internal container.
        host: '0.0.0.0',
        port: Number(process.env.VITE_PORT ?? 5173),
        strictPort: true,
        hmr: { host: process.env.VITE_HMR_HOST ?? 'localhost' },
        watch: {
            // Polling dibutuhkan pada bind mount Docker di macOS/Windows:
            // peristiwa inotify dari host tidak sampai ke dalam container.
            usePolling: process.env.VITE_POLL === 'true',
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
