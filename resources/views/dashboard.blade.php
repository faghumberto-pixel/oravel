{{-- --- INTERVENÇÃO MÍNIMA PARA FILAMENT: INÍCIO --- --}}
{{-- Substituímos o layout genérico pelo layout de página do Filament --}}
<x-filament-panels::page>
{{-- --- INTERVENÇÃO MÍNIMA PARA FILAMENT: FIM --- --}}

    {{-- TODO O CONTEÚDO CUSTOMIZADO DO DASHBOARD QUE JÁ FIZEMOS É MANTIDO AQUI --}}
    {{-- (Cabeçalho, cards de resumo, grid de evidências auditáveis, mapa integrado, etc.) --}}
    
    <div class="py-6">
        {{-- ... conteúdo customizado mantido ... --}}
    </div>

    {{-- --- INTERVENÇÃO MÍNIMA PARA FILAMENT: INÍCIO --- --}}
    {{-- Leaflet assets e scripts customizados são mantidos aqui dentro --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // ... script dinâmico do mapa mantido ...
    </script>
{{-- --- INTERVENÇÃO MÍNIMA PARA FILAMENT: FIM --- --}}

{{-- --- INTERVENÇÃO MÍNIMA PARA FILAMENT: INÍCIO --- --}}
</x-filament-panels::page>
{{-- --- INTERVENÇÃO MÍNIMA PARA FILAMENT: FIM --- --}}