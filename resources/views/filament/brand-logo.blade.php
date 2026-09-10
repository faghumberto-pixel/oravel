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
        {{-- Monograma "O" branco em quadrado preto (2026-09) --}}
        <img src="{{ asset('images/oravel-logo-monograma-32.png') }}?v=1" alt="Oravel" class="h-8 w-8 shrink-0 rounded">

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
