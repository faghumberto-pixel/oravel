@if (\App\Support\Tour\Tour::para(auth()->user()))
    {{-- Reabre o passo a passo da tela atual (ou leva à primeira tela do tour). O pop-up é o componente Livewire tour-guiado. --}}
    <x-filament::icon-button
        color="gray"
        icon="heroicon-o-academic-cap"
        icon-size="lg"
        label="Passo a passo"
        tooltip="Passo a passo: o que esta tela mostra"
        x-data
        x-on:click="$dispatch('abrir-tour')"
    />
@endif
