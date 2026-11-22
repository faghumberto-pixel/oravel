@php
    $r = $this->resumo();
    $p = $r ? $this->plano($r) : null;
    $esforco = $p ? $this->esforco($p) : null;
    $f = fn ($v) => number_format($v, abs($v) >= 100 ? 0 : 1, ',', '.');
    $corP = ['Alta' => '#dc2626', 'Média' => '#d97706', 'Baixa' => '#2563eb'];
    $colunas = [30 => ['Primeiros 30 dias', 'Urgente: o que mais pesa na saúde da empresa', '#dc2626'], 60 => ['Até 60 dias', 'Importante: corrigir na sequência', '#d97706'], 90 => ['Até 90 dias', 'Consolidar: ajustes finos', '#2563eb']];
@endphp

<x-filament-panels::page>
    @if (! $r)
        <x-intelligence.vazio
            titulo="O caminho para uma locadora mais saudável"
            texto="O Plano de evolução transforma o diagnóstico em ações: o que fazer primeiro, até onde chegar e em quanto tempo. A cada nova importação você vê se o plano está funcionando."
            cor="#16a34a"
            :itens="[['Ações priorizadas', 'Em 30, 60 e 90 dias, da mais urgente para a mais leve.'], ['Metas claras', 'Onde você está hoje, a meta do ciclo e a meta ideal de cada indicador.'], ['Acompanhamento', 'Importe de novo e meça o avanço contra o plano.']]"
            :url="$this->urlImportacoes()" />
    @elseif (! $p['itens'])
        <x-filament::section>
            <div style="text-align:center;padding:22px">
                <div style="font-size:2.4rem">🎯</div>
                <b style="font-size:1.1rem;color:#16a34a">Todos os indicadores com nota estão em 80 ou mais.</b>
                <p style="opacity:.75;font-size:.9rem;margin:6px 0 0">Não há ação urgente. Mantenha a rotina e importe a próxima planilha para acompanhar.</p>
            </div>
        </x-filament::section>
    @else
        <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr))">
            <x-filament::section>
                <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;opacity:.6">Foco do ciclo</div>
                <div style="font-size:1.5rem;font-weight:800;margin:4px 0;color:#4f46e5">{{ $p['foco'] }}</div>
                <p style="font-size:.84rem;opacity:.8;line-height:1.5;margin:0">É a área com a menor nota agora. Resolver as ações dela é o que mais move o índice geral.</p>
                <div style="margin-top:12px;font-size:.8rem;opacity:.85"><b>{{ count($p['itens']) }}</b> {{ count($p['itens']) == 1 ? 'ação' : 'ações' }} no plano · reimporte a planilha em 30 dias para medir o avanço.</div>
            </x-filament::section>
            @if ($esforco)
                <x-filament::section heading="Esforço por prazo"><x-intelligence.grafico :cfg="$esforco" :altura="190" /></x-filament::section>
            @endif
        </div>

        <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),1fr));align-items:start">
            @foreach ($colunas as $dias => [$titulo, $sub, $cc])
                <div>
                    <div style="border-radius:14px 14px 0 0;padding:12px 16px;color:#fff;background:linear-gradient(135deg,{{ $cc }},{{ $cc }}bb)">
                        <div style="font-weight:800">{{ $titulo }}</div><div style="font-size:.74rem;opacity:.9">{{ $sub }}</div>
                    </div>
                    <div style="display:grid;gap:10px;padding:10px;border:1px solid {{ $cc }}44;border-top:0;border-radius:0 0 14px 14px">
                        @forelse ($p['prazos'][$dias] as $i)
                            <div style="border-radius:12px;padding:12px 14px;border:1px solid rgba(128,128,128,.25);background:{{ $cc }}0a">
                                <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                                    <b style="font-size:.88rem">{{ $i['titulo'] }}</b>
                                    <span style="font-size:.64rem;font-weight:700;color:#fff;background:{{ $corP[$i['prioridade']] }};border-radius:999px;padding:2px 9px">{{ $i['prioridade'] }}</span>
                                </div>
                                <div style="font-size:.7rem;opacity:.6;margin-bottom:8px">{{ $i['area_rotulo'] }}</div>
                                <div style="display:flex;align-items:center;gap:6px;font-size:.78rem;margin-bottom:8px">
                                    <span style="text-align:center"><b style="font-size:1rem">{{ $f($i['valor']) }}</b><br><span style="opacity:.6;font-size:.64rem">hoje</span></span>
                                    <span style="flex:1;border-top:2px dashed {{ $cc }}"></span>
                                    <span style="text-align:center"><b style="font-size:1rem;color:{{ $cc }}">{{ $f($i['alvo_ciclo']) }}</b><br><span style="opacity:.6;font-size:.64rem">meta do ciclo</span></span>
                                    <span style="flex:1;border-top:2px dashed #16a34a"></span>
                                    <span style="text-align:center"><b style="font-size:1rem;color:#16a34a">{{ $f($i['meta']) }}</b><br><span style="opacity:.6;font-size:.64rem">ideal</span></span>
                                </div>
                                <ul style="margin:0;padding-left:18px;font-size:.8rem;line-height:1.55">@foreach ($i['acoes'] as $a) <li>{{ $a }}</li> @endforeach</ul>
                            </div>
                        @empty
                            <p style="font-size:.8rem;opacity:.6;margin:6px;text-align:center">Nenhuma ação neste prazo.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
        <p style="font-size:.72rem;opacity:.55;margin:0">Os textos de ação são orientações gerais para locadoras e as metas são referências iniciais: ajuste-as à realidade da sua empresa.</p>
    @endif
</x-filament-panels::page>
