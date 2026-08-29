import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/seat-editor.js',
                'resources/js/seat-picker.js',
                'resources/js/admin-seat-picker.js',
                'resources/js/admin-seat-availability.js',
                'resources/js/openpay-checkout.js',
                'resources/js/package-scanner.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Force IPv4 loopback. Without this, Vite/Node can resolve
        // "localhost" to the IPv6 address ([::1]) and advertise that in
        // public/hot — if the browser's IPv6 loopback resolution is at all
        // flaky, the module script tag silently fails to load and NONE of
        // the editor's JS runs, with no obvious error.
        host: '127.0.0.1',
    },
});
