import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
          input: [
            'resources/css/common.css',
            'resources/js/chat.js',
            'resources/js/beacon.js',
            'resources/js/widget.js',
            'resources/js/console.js',
          ],
          refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
