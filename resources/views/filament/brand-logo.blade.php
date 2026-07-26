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
    <div class="fi-oravel-brand-logo-row flex items-center gap-4">
        {{-- Cor do "r" fixa na laranja da marca (nao text-primary-500) --
             central usa azul como cor primaria do painel, ficaria com o
             "r" azul se dependesse da cor primaria de cada painel. Mesmo
             #E8541A usado nos marcadores dos mapas comerciais. --}}
        <div class="text-xl font-bold tracking-tight text-white">O<span style="color: #E8541A">r</span>avel</div>

        @if($tenant)
            <span class="text-sm font-bold tracking-tight text-primary-500 truncate max-w-[8rem]">{{ $tenant->name }}</span>
        @endif
    </div>

    @if($tenant && $segmentLabel)
        <span class="flex items-center gap-1.5 text-[11px] font-medium tracking-wide text-gray-400 truncate max-w-[12rem]">
            <span class="inline-block h-1.5 w-1.5 rounded-full {{ $segmentDotClass }} shrink-0"></span>
            {{ $segmentLabel }}
        </span>
    @endif
</div>
