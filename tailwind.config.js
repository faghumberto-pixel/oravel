import preset from './vendor/filament/filament/tailwind.config.preset';
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Sem isso, o default do Tailwind e' 'media' (@media prefers-color-scheme,
    // depende do SO de quem olha) -- mas o Filament aplica dark mode via
    // classe .dark no <html> (inclusive forcado sempre no painel Central,
    // ->darkMode(true, isForced: true)). Toda variante dark: literal usada
    // no PROJETO (fora dos componentes internos do pacote filament/*, que
    // tem seu proprio build com essa config ja certa) ficava dependente da
    // sorte do tema do SO em vez da classe real -- causa raiz de "cor de
    // fundo por estagio nao aparece" em SalesLeadResource (2026-08-05):
    // dark:bg-blue-500/25 compilava como seletor de classe solto
    // (.dark\:bg-blue-500\/25, sem o .dark pai), nunca vencendo contra
    // bg-blue-50 (sempre ativo) na mesma especificidade.
    darkMode: 'class',
    // preset do Filament (2a causa raiz do mesmo bug 2026-08-05): sem ele,
    // classes que o Filament monta em runtime pra cor customizada
    // (text-custom-400/600 + --c-{tom} inline, usadas por ->color('crmOrange')
    // etc) nunca eram geradas -- o TEXTO da classe so aparece literal DENTRO
    // dos .blade.php do proprio pacote (ex: vendor/filament/tables/.../
    // text-column.blade.php), nunca em codigo do projeto. resources/css/
    // filament/admin/tailwind.config.js ja tinha isso certo (por isso o
    // texto laranja "funcionava" no admin mas nao no central, que usa este
    // config raiz sem o preset). vendor/filament/**/*.blade.php escaneia
    // essas views pra essas classes existirem de verdade no CSS final.
    presets: [preset],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/filament/**/*.blade.php',
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
