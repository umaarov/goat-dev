import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // port: 5175,
        server: {
            // origin: 'http://127.0.0.1:8000',
            host: '0.0.0.0',
            port: 5173,
        },
        hmr: {
            // overlay: false,
            host: 'localhost'
        },
        watch: {
            usePolling: true
        }
    },
    worker: {
        format: 'es',
        plugins: [
            laravel({
                input: ['resources/js/workers/renderer.worker.js'],
                refresh: true,
            }),
        ],
    },

    build: {
        sourcemap: true,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('three')) {
                            return 'vendor-three';
                        }
                        return 'vendor';
                    }
                }
            }
        }
    },
});
