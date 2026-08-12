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
        {{-- Wordmark ORAveL (2026-08) -- so' o texto no header, sem o
             monograma OR sobreposto (esse fica reservado pro favicon/icone,
             onde funciona sozinho; em tamanho pequeno de topbar os dois
             juntos ficavam ilegiveis). Padrao de caixa ORA-ve-L e' proposital,
             parte da marca -- nao e' erro de digitacao. --}}
        <svg viewBox="0 0 460 130" class="h-6 w-auto" role="img" aria-label="Oravel">
            <text x="0" y="98" font-family="Arial, 'Helvetica Neue', Helvetica, sans-serif" font-weight="700" font-size="76" fill="currentColor" letter-spacing="-1" class="text-white">ORA<tspan font-weight="600">ve</tspan>L</text>
        </svg>

        @if($tenant)
            <span class="text-xs font-bold tracking-tight text-primary-500 truncate max-w-[16rem]">{{ $tenant->name }}</span>
        @endif
    </div>

    @if($tenant && $segmentLabel)
        <span class="flex items-center gap-1.5 text-[11px] font-medium tracking-wide text-gray-400 truncate max-w-[16rem]">
            <span class="inline-block h-1.5 w-1.5 rounded-full {{ $segmentDotClass }} shrink-0"></span>
            {{ $segmentLabel }}
        </span>
    @endif
</div>
