@php
    $tenant = \App\Support\Tenancy::current();
    $modulo = $tenant ? collect(\App\Support\Pacotes::produtosDoTenant($tenant))->map(fn ($c) => \App\Support\Pacotes::nome($c))->implode(' + ') : null;
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
{{-- 19/10/2026 (pedido do usuário): o logotipo "OR" saiu daqui e foi para o menu lateral, ao lado das 3 bolinhas; na frente do nome da
     empresa entra o nome do pacote contratado ("Módulo: Manutenção"; com mais de um produto, "Manutenção + Vendas"). --}}
<div class="flex items-center gap-2 shrink-0">
    @if($tenant)
        <div class="flex flex-col leading-none">
            <span class="flex items-center gap-2">
                <span class="text-sm font-bold tracking-tight text-white truncate max-w-[10rem]">{{ $tenant->name }}</span>
                <span class="rounded-full bg-white/10 px-2 py-0.5 text-[11px] font-semibold text-orange-300 whitespace-nowrap" data-testid="modulo-contratado">Módulo: {{ $modulo }}</span>
            </span>
            @if($segmentLabel)
                <span class="mt-1 flex items-center gap-1 text-[10px] font-medium tracking-wide text-gray-400 truncate max-w-[12rem]">
                    <span class="inline-block h-1.5 w-1.5 rounded-full {{ $segmentDotClass }} shrink-0"></span>
                    {{ $segmentLabel }}
                </span>
            @endif
        </div>
    @endif
</div>
