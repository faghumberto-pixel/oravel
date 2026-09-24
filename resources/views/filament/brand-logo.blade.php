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
{{-- Sidebar/topbar header: logotipo Oravel ANTES do nome do tenant
     (2026-09-24, pedido do usuario). CORRIGIDO no mesmo dia: "em branco"
     era pedido de COR (branco), nao de deixar vazio -- nome do tenant
     volta a aparecer, so' trocou de laranja pra branco. Logo SEMPRE
     visivel (nao atras de @if($tenant)) -- no painel Central,
     Tenancy::current() e' null pra super admin sem "tenant atuante"
     selecionado (ver App\Support\Tenancy::current()), entao o logo sumia
     por inteiro la' antes desta mudanca. --}}
<div class="flex items-center gap-2 shrink-0">
    <img
        src="{{ asset('images/oravel-logo-or.png') }}"
        alt="Oravel"
        class="h-7 w-auto shrink-0"
    >
    @if($tenant)
        <div class="flex flex-col leading-none">
            <span class="text-base font-bold tracking-tight text-white truncate max-w-[12rem]">{{ $tenant->name }}</span>
            @if($segmentLabel)
                <span class="flex items-center gap-1 text-[10px] font-medium tracking-wide text-gray-400 truncate max-w-[12rem]">
                    <span class="inline-block h-1.5 w-1.5 rounded-full {{ $segmentDotClass }} shrink-0"></span>
                    {{ $segmentLabel }}
                </span>
            @endif
        </div>
    @endif
</div>
