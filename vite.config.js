import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

// https://vitejs.dev/config/
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
        rollupOptions: {
            input: {
                app: resolve(__dirname, 'resources/js/app.js'),
                subscriber: resolve(__dirname, 'resources/js/subscriber.js'),
                style: resolve(__dirname, 'resources/css/app.css'),
            },
        },
    },

    // Path resolution
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
            '~': resolve(__dirname, 'resources'),
        },
    },

    // CSS configuration
    css: {
        postcss: './postcss.config.js',
    },
});
