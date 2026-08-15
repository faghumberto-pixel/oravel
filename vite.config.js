import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/central-kanban.js', 'resources/js/hour-meter-offline.js', 'resources/js/hour-meter-public.js', 'resources/js/time-clock-offline.js', 'resources/js/chat-app.js', 'resources/css/filament/admin/theme.css', 'resources/css/filament/central/theme.css'],
            refresh: true,
        }),
    ],
});
