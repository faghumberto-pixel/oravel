{{-- Renderizado via renderHook BODY_END no CentralPanelProvider --}}
@if (request()->routeIs('filament.central.resources.prospects.index') && auth()->user()?->isSuperAdmin())
    <livewire:prospect-chat />
@endif