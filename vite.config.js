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
        hmr: {
            overlay: false
        },
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
