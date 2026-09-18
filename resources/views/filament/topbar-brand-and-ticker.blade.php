{{-- So' a marca (logo+tenant): avisos foram extraidos pra
     topbar-announcements-ticker.blade.php (2026-09-18), que renderiza
     centralizado no topbar independente deste bloco -- ver
     vendor/filament-panels/components/topbar/index.blade.php. Este arquivo
     inteiro so' e' incluido (via TOPBAR_START) em modo topNavigation(). --}}
<a href="{{ filament()->getUrl() }}" class="flex items-center shrink-0">
    @include('filament.brand-logo')
</a>
