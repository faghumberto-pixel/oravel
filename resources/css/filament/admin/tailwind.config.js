import preset from '../../../../vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './app/Livewire/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './resources/views/livewire/**/*.blade.php',
        // Views do Filament ejetadas (publicadas em resources/views/vendor/...,
        // ex. components/topbar/index.blade.php) nao eram escaneadas -- classes
        // Tailwind usadas so' nelas nunca eram geradas de verdade, so' pareciam
        // funcionar quando por acaso a mesma classe ja era usada em algum
        // arquivo dos globs acima.
        './resources/views/vendor/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        // layouts/checklist-mobile.blade.php (Modo Campo) + standalone
        // (hour-meter-offline, portaria/*, etc) ficavam de fora de todos os
        // globs acima -- mesmo bug ja documentado pra resources/views/vendor,
        // agora achado em layouts/ e nas views soltas na raiz de views/.
        './resources/views/layouts/**/*.blade.php',
        './resources/views/*.blade.php',
        './resources/views/portaria/**/*.blade.php',
        './resources/views/checkout/**/*.blade.php',
    ],
}
