@php
    $d = $this->dados();
    $rot = \App\Domain\Intelligence\Services\AnaliseDeEquipamentos::ROTULOS;
    $cc = \App\Domain\Intelligence\Support\Graficos::CLASSE_COR;
    $grade = 'display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));align-items:stretch';
    $brl = fn ($v) => $v === null ? '—' : 'R$ '.number_format($v, 0, ',', '.');
    $pct = fn ($v, $c = 1) => $v === null ? '—' : number_format($v, $c, ',', '.').'%';
    $setaT = ['piorando' => ['▼', '#dc2626'], 'melhorando' => ['▲', '#16a34a'], 'estavel' => ['■', '#94a3b8']];
@endphp

<x-filament-panels::page>
    @if (! $d)
        <x-intelligence.vazio
            titulo="Quais equipamentos explicam os seus números"
            texto="Envie a aba “Equipamentos” da planilha (uma linha por equipamento) e o Intelligence mostra, um a um, o que revisar, o que vender ou trocar, o que está parado sem contrato e onde as corretivas se repetem."
            cor="#7c3aed"
            :itens="[['Considerar trocar ou vender', 'Equipamentos cujo custo de manutenção já pesa demais frente ao valor, com o motivo e o número.'], ['Subutilizados', 'Bons equipamentos parados sem contrato: oferte ou venda.'], ['Reincidência e risco de parada', 'Onde o mesmo defeito volta e o que pode parar a operação.']]"
            :url="$this->urlImportacoes()" />
    @else
        @php $r = $d['resumo']; $c = $r['por_classe']; @endphp

        <div style="font-size:.82rem;opacity:.75">Fotografia de {{ \Carbon\Carbon::parse($d['snapshot']['data'])->format('d/m/Y') }} · {{ number_format($r['total'], 0, ',', '.') }} equipamentos analisados</div>

        {{-- Cartões: clique filtra a tabela --}}
        <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(min(100%,170px),1fr))">
            @foreach (['trocar_vender', 'revisar', 'risco_parada', 'subutilizado', 'saudavel'] as $k)
                @php $ativo = $this->classificacao === $k; @endphp
                <button type="button" wire:click="filtrarCard('{{ $k }}')" style="text-align:left;border:0;cursor:pointer;border-radius:14px;padding:14px 16px;color:#fff;background:linear-gradient(135deg,{{ $cc[$k] }},{{ $cc[$k] }}bb);box-shadow:0 6px 16px -8px {{ $cc[$k] }};outline:{{ $ativo ? '3px solid #0f172a' : 'none' }};outline-offset:2px">
                    <div style="font-size:.72rem;opacity:.92;font-weight:600">{{ $rot[$k] }}</div>
                    <div style="font-size:2rem;font-weight:800;line-height:1.1">{{ $c[$k] ?? 0 }}</div>
                    @if ($k === 'trocar_vender' && $r['valor_trocar_vender'] > 0)<div style="font-size:.72rem;opacity:.92">{{ $brl($r['valor_trocar_vender']) }} em valor</div>
                    @else<div style="font-size:.72rem;opacity:.85">{{ ($c[$k] ?? 0) == 1 ? 'equipamento' : 'equipamentos' }}</div>@endif
                </button>
            @endforeach
        </div>

        {{-- Gráficos --}}
        <div style="{{ $grade }}">
            @if ($d['graficos']['pareto'])
                <x-filament::section heading="Pareto do custo de manutenção" :description="'Os '.count($d['pareto']['itens']).' equipamentos de maior custo no mês concentram '.number_format($d['pareto']['topo_pct'], 0).'% do custo da frota.'">
                    <x-intelligence.grafico :cfg="$d['graficos']['pareto']" :altura="320" />
                </x-filament::section>
            @endif
            @if ($d['graficos']['bolhas'])
                <x-filament::section heading="Valor × receita" description="Tamanho = custo do mês. Equipamento caro e de receita baixa fica embaixo à direita.">
                    <x-intelligence.grafico :cfg="$d['graficos']['bolhas']" :altura="320" />
                </x-filament::section>
            @endif
        </div>

        {{-- Tabela --}}
        <x-filament::section heading="Todos os equipamentos">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px">
                <label style="font-size:.74rem;font-weight:600;flex:1;min-width:200px">Buscar
                    <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Patrimônio ou nome" style="display:block;width:100%;margin-top:4px;padding:8px 12px;border-radius:10px;border:1px solid rgba(128,128,128,.4);background:transparent">
                </label>
                <label style="font-size:.74rem;font-weight:600">Categoria
                    <select wire:model.live="categoria" style="display:block;margin-top:4px;padding:8px 12px;border-radius:10px;border:1px solid rgba(128,128,128,.4);background:transparent">
                        <option value="">Todas</option>
                        @foreach ($r['categorias'] as $cat) <option value="{{ $cat }}">{{ $cat }}</option> @endforeach
                    </select>
                </label>
                <label style="font-size:.74rem;font-weight:600">Situação
                    <select wire:model.live="classificacao" style="display:block;margin-top:4px;padding:8px 12px;border-radius:10px;border:1px solid rgba(128,128,128,.4);background:transparent">
                        <option value="">Todas</option>
                        @foreach ($rot as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                    </select>
                </label>
                <label style="font-size:.74rem;font-weight:600">Ordenar por
                    <select wire:model.live="ordem" style="display:block;margin-top:4px;padding:8px 12px;border-radius:10px;border:1px solid rgba(128,128,128,.4);background:transparent">
                        @foreach (\App\Domain\Intelligence\Services\EquipamentosService::ORDENS as $k => $l) <option value="{{ $k }}">{{ $l }}</option> @endforeach
                    </select>
                </label>
                <x-filament::button wire:click="exportar" color="gray" icon="heroicon-o-arrow-down-tray" size="sm">Excel</x-filament::button>
            </div>

            <div style="overflow-x:auto">
                <table style="width:100%;font-size:.82rem;border-collapse:collapse;min-width:900px">
                    <thead><tr style="text-align:left;opacity:.6;font-size:.7rem">
                        <th style="padding:6px 8px">Equipamento</th><th>Situação</th><th style="text-align:right">Valor</th><th style="text-align:right">Utilização</th><th style="text-align:right">Retorno/ano</th><th style="text-align:right">Custo ÷ valor</th><th style="text-align:right">Reincid.</th><th style="text-align:center">Tend.</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($d['tabela']['linhas'] as $e)
                            @php $cor = $cc[$e['classificacao']] ?? '#94a3b8'; $t = $setaT[$e['tendencia'] ?? ''] ?? null; @endphp
                            <tr style="border-top:1px solid rgba(128,128,128,.2)" title="{{ $e['motivo'] }}">
                                <td style="padding:8px">
                                    <a href="{{ \App\Filament\Pages\IntelligenceEquipamento::getUrl(['patrimonio' => $e['patrimonio']]) }}" style="font-weight:700;text-decoration:none;color:#4f46e5">{{ $e['patrimonio'] }}</a>
                                    <div style="font-size:.72rem;opacity:.7">{{ $e['nome'] }}@if ($e['categoria']) · {{ $e['categoria'] }}@endif</div>
                                </td>
                                <td><span style="display:inline-block;font-size:.68rem;font-weight:700;color:#fff;background:{{ $cor }};border-radius:999px;padding:2px 10px;white-space:nowrap">{{ $rot[$e['classificacao']] ?? $e['classificacao'] }}</span></td>
                                <td style="text-align:right">{{ $brl($e['valor']) }}</td>
                                <td style="text-align:right">{{ $pct($e['utilizacao_pct'], 0) }}</td>
                                <td style="text-align:right;color:{{ ($e['retorno_anual_pct'] ?? 0) < 0 ? '#dc2626' : 'inherit' }}">{{ $pct($e['retorno_anual_pct'], 0) }}</td>
                                <td style="text-align:right;font-weight:600;color:{{ ($e['razao_custo_valor'] ?? 0) >= 0.6 ? '#dc2626' : (($e['razao_custo_valor'] ?? 0) >= 0.4 ? '#d97706' : 'inherit') }}">{{ $e['razao_custo_valor'] === null ? '—' : $pct($e['razao_custo_valor'] * 100, 0) }}</td>
                                <td style="text-align:right">{{ $e['reincidencias'] !== null && $e['os_corretivas'] ? $e['reincidencias'].'/'.$e['os_corretivas'] : '—' }}</td>
                                <td style="text-align:center;color:{{ $t[1] ?? 'inherit' }}">{{ $t[0] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="padding:18px;text-align:center;opacity:.7">Nenhum equipamento com esse filtro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;font-size:.78rem;opacity:.85">
                <span>Mostrando {{ count($d['tabela']['linhas']) }} de {{ number_format($d['tabela']['total'], 0, ',', '.') }}</span>
                @if (count($d['tabela']['linhas']) < $d['tabela']['total'])
                    <x-filament::button wire:click="mostrarMais" color="gray" size="sm">Mostrar mais 50</x-filament::button>
                @endif
            </div>
        </x-filament::section>
        <p style="font-size:.72rem;opacity:.55;margin:0">As classificações usam limiares de referência (custo ÷ valor de 40% e 60%, os mesmos do ERP): servem para apontar onde olhar, não substituem a decisão técnica e comercial.</p>
    @endif
</x-filament-panels::page>
