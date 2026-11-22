@php
    $r = $this->resumo();
    $g = $r ? $this->graficos($r) : [];
    $grade = 'display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));align-items:stretch';
@endphp

<x-filament-panels::page>
    @if (! $r)
        <x-intelligence.vazio
            titulo="A operação da sua locadora em um só lugar"
            texto="Frota, dinheiro e oficina lado a lado: quanto da frota está rendendo, para onde vai o custo e como a oficina está respondendo."
            cor="#0ea5e9"
            :itens="[['Frota', 'Locados, disponíveis e indisponíveis, e a disponibilidade ao longo do tempo.'], ['Finanças', 'Receita, custos e resultado de cada mês, e a composição do custo.'], ['Oficina', 'Corretivas contra preventivas e o tempo médio de reparo.']]"
            :url="$this->urlImportacoes()" />
    @else
        @if ($g['kpis'])
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(min(100%,190px),1fr))">
                @foreach ($g['kpis'] as $k) <x-intelligence.kpi :k="$k" /> @endforeach
            </div>
        @endif

        <div style="font-weight:800;font-size:1.05rem;color:#0ea5e9">Frota</div>
        <div style="{{ $grade }}">
            @if ($g['frota']) <x-filament::section heading="Frota hoje" description="Quanto da frota está gerando receita."><x-intelligence.grafico :cfg="$g['frota']" :altura="290" /></x-filament::section> @endif
            @if ($g['frota_tempo']) <x-filament::section heading="Disponibilidade e utilização"><x-intelligence.grafico :cfg="$g['frota_tempo']" :altura="290" /></x-filament::section> @endif
        </div>

        <div style="font-weight:800;font-size:1.05rem;color:#16a34a">Finanças</div>
        <div style="{{ $grade }}">
            @if ($g['financeiro']) <x-filament::section heading="Receita, custos e resultado"><x-intelligence.grafico :cfg="$g['financeiro']" :altura="290" /></x-filament::section> @endif
            @if ($g['custos']) <x-filament::section heading="Para onde vai o custo do mês"><x-intelligence.grafico :cfg="$g['custos']" :altura="290" /></x-filament::section> @endif
        </div>

        <div style="font-weight:800;font-size:1.05rem;color:#d97706">Oficina</div>
        <div style="{{ $grade }}">
            @if ($g['oficina']) <x-filament::section heading="Corretivas × preventivas e tempo de reparo"><x-intelligence.grafico :cfg="$g['oficina']" :altura="300" /></x-filament::section> @endif
        </div>
    @endif
</x-filament-panels::page>
