import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google, local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Headings: Newsreader cut at its 48pt optical size, where its contrast is finest. One static weight,
                // 23 KB, instead of the 130 KB variable font.
                local('Newsreader', {
                    variants: [{ src: 'resources/fonts/Newsreader-Display-Regular.woff2', weight: 400 }],
                    fallbacks: ['Iowan Old Style', 'Palatino Linotype', 'Georgia'],
                }),
                // Text: an open, friendly sans that stays clear at phone sizes.
                google('Figtree', {
                    weights: [400, 600],
                    preload: [{ weight: 400 }],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
