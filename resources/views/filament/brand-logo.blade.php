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
        {{-- Monograma OR (2026-08) -- O branco outline + R laranja outline,
             lado a lado sem sobrepor (ver historico: bea4af3 sobrepunha a
             haste do R na curva do O e ficava ilegivel em favicon pequeno,
             revertido em bc259c5). SVG inline pra ficar nitido em qualquer
             tamanho, sem depender de raster. --}}
        <svg viewBox="0 0 100 100" class="h-6 w-6 shrink-0" role="img" aria-label="Símbolo Oravel">
            <circle cx="34" cy="50" r="20" fill="none" stroke="#FFFFFF" stroke-width="8"/>
            <path d="M62 28 L62 72 M62 28 L76 28 A11 11 0 0 1 76 50 L62 50 M72 50 L82 72"
                fill="none" stroke="#E8541A" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        {{-- Wordmark: "OR" em laranja (mesma cor #E8541A usada nos
             marcadores dos mapas comerciais e no monograma acima), resto
             em branco. Nao depende de text-primary-500 pelo mesmo motivo
             de sempre -- central usa azul como cor primaria do painel,
             ficaria com a marca azul se dependesse disso. --}}
        <div class="text-xl font-bold tracking-tight text-white"><span style="color: #E8541A">OR</span>Ave<span>L</span></div>

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
