import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Adicionado 2026-07-28: App\Support\CrmPalette centraliza as
        // classes Tailwind literais de cor por estagio/segmento do CRM
        // (Kanban, Funil de Vendas) num unico arquivo PHP -- sem isso, cada
        // classe so' e' compilada se aparecer literal em ALGUM .blade.php,
        // forcando duplicar o mesmo array em cada view (ver comentario em
        // kanban.blade.php/funil-vendas.blade.php sobre esse problema).
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
