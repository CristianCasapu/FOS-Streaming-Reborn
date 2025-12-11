import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Custom plugin to manage the 'hot' file for PHP integration
 * - Creates public/hot when dev server starts (contains dev server URL)
 * - Removes public/hot when dev server stops
 * - PHP vite() helper reads this file to detect dev mode
 */
function hotFilePlugin() {
    const hotFilePath = path.resolve(process.cwd(), 'public/hot');

    return {
        name: 'hot-file',
        configureServer(server) {
            // Write hot file when server starts
            server.httpServer?.once('listening', () => {
                const address = server.httpServer?.address();
                const protocol = server.config.server.https ? 'https' : 'http';
                const host = server.config.server.host || 'localhost';
                const port = typeof address === 'object' ? address?.port : 5173;
                const url = `${protocol}://${host === '0.0.0.0' ? 'localhost' : host}:${port}`;

                fs.writeFileSync(hotFilePath, url);
                console.log(`\n  Hot file created: ${hotFilePath}`);
            });

            // Remove hot file when server closes
            const cleanup = () => {
                if (fs.existsSync(hotFilePath)) {
                    fs.unlinkSync(hotFilePath);
                    console.log(`\n  Hot file removed: ${hotFilePath}`);
                }
            };

            process.on('exit', cleanup);
            process.on('SIGINT', () => { cleanup(); process.exit(); });
            process.on('SIGTERM', () => { cleanup(); process.exit(); });
        },
    };
}

// https://vite.dev/config/
export default defineConfig({
    plugins: [
        vue(),
        hotFilePlugin(),
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
