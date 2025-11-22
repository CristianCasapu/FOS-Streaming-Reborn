import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

// https://vite.dev/config/
export default defineConfig({
    plugins: [
        vue(),
    ],

    // Development server configuration
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: false,
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: true,
        },
    },

    // Build configuration
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: true,
        // Vite 7 uses modern browser targets by default (baseline-widely-available)
        target: 'esnext',
        rollupOptions: {
            input: {
                app: fileURLToPath(new URL('./resources/js/app.js', import.meta.url)),
                subscriber: fileURLToPath(new URL('./resources/js/subscriber.js', import.meta.url)),
                style: fileURLToPath(new URL('./resources/css/app.css', import.meta.url)),
            },
        },
    },

    // Path resolution - Updated to use fileURLToPath for ESM compatibility
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            '~': fileURLToPath(new URL('./resources', import.meta.url)),
        },
    },

    // CSS configuration
    css: {
        postcss: './postcss.config.js',
    },
});
