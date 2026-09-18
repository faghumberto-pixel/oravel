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
{{-- Topbar unificado (2026-09-18): logo + tenant em linha horizontal única. --}}
<div class="flex items-center gap-2 shrink-0">
    {{-- Monograma "O" branco em quadrado preto (2026-09) --}}
    <img src="{{ asset('images/oravel-logo-monograma-32.png') }}?v=1" alt="Oravel" class="h-8 w-8 shrink-0 rounded">

    @if($tenant)
        <div class="flex flex-col leading-none">
            <span class="text-xs font-bold tracking-tight text-orange-500 truncate max-w-[12rem]">{{ $tenant->name }}</span>
            @if($segmentLabel)
                <span class="flex items-center gap-1 text-[10px] font-medium tracking-wide text-gray-400 truncate max-w-[12rem]">
                    <span class="inline-block h-1.5 w-1.5 rounded-full {{ $segmentDotClass }} shrink-0"></span>
                    {{ $segmentLabel }}
                </span>
            @endif
        </div>
    @endif
</div>
