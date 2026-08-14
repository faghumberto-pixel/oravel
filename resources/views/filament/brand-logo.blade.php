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
<div class="flex flex-col leading-tight">
    <div class="fi-oravel-brand-logo-row flex items-center gap-3">
        {{-- Wordmark "Oravel" (2026-08) -- recorte do logo oficial sem o
             monograma OR, so' o texto. Corte no espaco real entre os dois
             blocos (x=98 a 349 da imagem original 351x68), sem cortar
             nenhuma letra. --}}
        <img src="{{ asset('images/oravel-wordmark-only.png') }}?v=1" alt="Oravel" class="h-5 w-auto shrink-0">

        @if($tenant)
            <span class="text-xs font-bold tracking-tight text-gray-300 truncate max-w-[16rem]">{{ $tenant->name }}</span>
        @endif
    </div>

    @if($tenant && $segmentLabel)
        <span class="flex items-center gap-1.5 text-[11px] font-medium tracking-wide text-gray-400 truncate max-w-[16rem]">
            <span class="inline-block h-1.5 w-1.5 rounded-full {{ $segmentDotClass }} shrink-0"></span>
            {{ $segmentLabel }}
        </span>
    @endif
</div>
