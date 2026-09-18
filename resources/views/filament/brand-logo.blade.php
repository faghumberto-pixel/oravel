@php
    $tenant = \App\Support\Tenancy::current();
    $segmentLabel = $tenant?->segment ? (\App\Models\Client::nicheLabels()[$tenant->segment] ?? null) : null;
    // Cores fixas por segmento -- so' um pontinho ao lado do rotulo.
    $segmentDotClass = match ($tenant?->segment) {
        \App\Models\Client::NICHE_EVENTOS => 'bg-violet-500',
        \App\Models\Client::NICHE_CONSTRUCAO_CIVIL => 'bg-sky-500',
        \App\Models\Client::NICHE_INDUSTRIAL_HOSPITALAR => 'bg-teal-500',
        default => 'bg-gray-500',
    };
@endphp
{{-- Sidebar header: so' o nome do tenant (2026-09-18, pedido do usuario --
     "retire o logotipo do lado do tenant"). Monograma removido daqui;
     marca "ORAVEL ERP" continua no topbar (ver
     vendor/filament-panels/components/topbar/index.blade.php). --}}
<div class="flex items-center gap-2 shrink-0">
    @if($tenant)
        <div class="flex flex-col leading-none">
            <span class="text-base font-bold tracking-tight text-orange-500 truncate max-w-[12rem]">{{ $tenant->name }}</span>
            @if($segmentLabel)
                <span class="flex items-center gap-1 text-[10px] font-medium tracking-wide text-gray-400 truncate max-w-[12rem]">
                    <span class="inline-block h-1.5 w-1.5 rounded-full {{ $segmentDotClass }} shrink-0"></span>
                    {{ $segmentLabel }}
                </span>
            @endif
        </div>
    @endif
</div>
