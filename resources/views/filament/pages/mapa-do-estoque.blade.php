<x-filament-panels::page>
    @include('dashboards.styles')

    <style>
        .me-barra { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; align-items: end; }
        .me-barra label { font-size: 11px; font-weight: 700; opacity: .7; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 4px; }
        .me-barra select, .me-barra input { width: 100%; padding: 8px 10px; border-radius: 8px; font-size: 13px; border: 1px solid var(--odb-border); background: transparent; color: inherit; }
        .me-barra select option { color: #111; }
        .me-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0; }
        .me-chip { display: inline-flex; align-items: center; gap: 7px; padding: 6px 13px; border-radius: 999px; font-size: 12.5px; font-weight: 600; cursor: pointer; border: 1px solid var(--odb-border); background: transparent; color: inherit; }
        .me-chip i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
        .me-chip.on { background: var(--odb-accent); border-color: var(--odb-accent); color: #fff; }
        .me-leg { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--odb-muted); margin: 4px 0 12px; }
        .me-leg b { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 6px; vertical-align: -2px; }
        .me-planta { display: flex; gap: 14px; overflow-x: auto; padding-bottom: 8px; align-items: flex-start; }
        .me-col { flex: 0 0 auto; background: var(--odb-card); border: 1px solid var(--odb-border); border-radius: 10px; padding: 12px; min-width: 170px; }
        .me-col h4 { margin: 0 0 2px; font-size: 14px; font-weight: 800; }
        .me-col small { display: block; color: var(--odb-muted); font-size: 11px; margin-bottom: 10px; }
        .me-prat { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
        .me-prat span.l { width: 30px; font-size: 10.5px; font-weight: 700; color: var(--odb-muted); text-align: right; }
        .me-cel { position: relative; width: 44px; height: 38px; border-radius: 6px; border: 2px solid transparent; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; font-size: 10px; font-weight: 800; line-height: 1.05; transition: transform .1s, opacity .15s; }
        .me-cel:hover { transform: scale(1.1); z-index: 2; }
        .me-cel.sel { border-color: #fff; box-shadow: 0 0 0 2px var(--odb-accent); transform: scale(1.12); z-index: 3; }
        .me-cel.apagada { opacity: .18; }
        .me-cel small { font-size: 9px; font-weight: 600; opacity: .9; margin: 0; }
        .me-det { display: grid; gap: 10px; }
        .me-det table { width: 100%; font-size: 13px; border-collapse: collapse; }
        .me-det th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; opacity: .65; padding: 6px 8px; border-bottom: 1px solid var(--odb-border); }
        .me-det td { padding: 8px; border-bottom: 1px solid var(--odb-border); vertical-align: top; }
        .me-det td.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .me-btn { display: inline-flex; padding: 6px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 700; border: 1px solid var(--odb-border); text-decoration: none; color: inherit; cursor: pointer; background: transparent; }
    </style>

    <div class="odb">
        <p class="odb-lead">O almoxarifado visto de cima: cada corredor é uma coluna, com as prateleiras e os níveis de cada uma. Clique num endereço para ver o que está nele; busque um item para saber onde ele está.</p>

        <section class="odb-w" style="--span: 12; margin-bottom: 12px">
            <div class="me-barra">
                <div><label>Almoxarifado</label>
                    <select wire:model.live="unidade">@foreach ($unidades as $id => $nome)<option value="{{ $id }}">{{ $nome }}</option>@endforeach</select></div>
                <div><label>Colorir por</label>
                    <select wire:model.live="modo">
                        <option value="situacao">Situação do estoque</option>
                        <option value="grupo">Grupo do item</option>
                        <option value="contagem">Contagem física em andamento</option>
                    </select></div>
                <div><label>Onde está o item?</label><input type="search" wire:model.live.debounce.300ms="busca" placeholder="Nome ou código do item"></div>
                <div><button type="button" class="me-btn" wire:click="limpar">Limpar filtros</button></div>
            </div>
            <div class="me-chips">
                @foreach ($grupos as $g)
                    <button type="button" wire:click="escolherGrupo('{{ $g->id }}')" @class(['me-chip', 'on' => $grupo === (string) $g->id])><i style="background: {{ $g->color ?: '#94a3b8' }}"></i>{{ $g->name }}</button>
                @endforeach
            </div>
        </section>

        <div class="odb-grid" style="margin-bottom: 14px">
            @foreach ([['Endereços', $mapa['resumo']['enderecos'], 'n'], ['Ocupados', $mapa['resumo']['ocupados'].' de '.$mapa['resumo']['enderecos'], 'n'], ['Vazios', $mapa['resumo']['vazios'], 'n'], ['Com item em falta', $mapa['resumo']['com_problema'], $mapa['resumo']['com_problema'] > 0 ? 'dn' : 'n']] as [$rotulo, $valor, $tom])
                <section class="odb-w" style="--span: 3"><div class="odb-wh"><h3>{{ $rotulo }}</h3></div><div class="odb-kpi">{{ $valor }}</div></section>
            @endforeach
        </div>

        <section class="odb-w" style="--span: 12">
            <div class="odb-wh"><h3>Planta do almoxarifado<small>@if ($busca || $grupo){{ $mapa['resumo']['destacados'] }} endereço(s) com o que você procura, os demais ficam apagados @else cada quadrado é um endereço (nível) @endif @if ($mapa['contagem']) · contagem aberta: {{ $mapa['contagem']['nome'] }} @endif</small></h3></div>
            <div class="me-leg">@foreach ($mapa['legenda'] as [$rotulo, $cor])<span><b style="background: {{ $cor }}"></b>{{ $rotulo }}</span>@endforeach</div>

            @if (count($mapa['colunas']) === 0)
                <p class="odb-empty">Nenhum endereço neste almoxarifado ainda. Cadastre em Materiais e Peças → Endereços do Almoxarifado (dá para gerar em lote).</p>
            @else
                <div class="me-planta">
                    @foreach ($mapa['colunas'] as $col)
                        <div class="me-col" wire:key="col-{{ $col['corredor'] }}">
                            <h4>Corredor {{ $col['corredor'] }}</h4>
                            <small>{{ $col['ocupados'] }}/{{ $col['total'] }} ocupados · R$ {{ number_format($col['valor'], 0, ',', '.') }}</small>
                            @foreach ($col['prateleiras'] as $prat)
                                <div class="me-prat">
                                    <span class="l">P{{ $prat['prateleira'] }}</span>
                                    @foreach ($prat['niveis'] as $cel)
                                        <button type="button" wire:click="selecionar('{{ $cel['id'] }}')" wire:key="cel-{{ $cel['id'] }}" title="{{ $cel['codigo'] }} · {{ count($cel['itens']) }} item(ns)"
                                                @class(['me-cel', 'sel' => $selecionado === $cel['id'], 'apagada' => ! $cel['destaque']]) style="background: {{ $cel['cor'] }}">
                                            N{{ $cel['nivel'] }}
                                            @if (count($cel['itens']) > 0)<small>{{ count($cel['itens']) }} it.</small>@endif
                                        </button>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($detalhe)
            <section class="odb-w me-det" style="--span: 12; margin-top: 14px">
                <div class="odb-wh" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
                    <h3>{{ $detalhe['codigo'] }}<small>{{ $detalhe['almoxarifado'] }}@if ($detalhe['descricao']) · {{ $detalhe['descricao'] }}@endif · R$ {{ number_format($detalhe['valor'], 2, ',', '.') }} em itens</small></h3>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a class="me-btn" target="_blank" href="{{ route('estoque.etiquetas', ['tipo' => 'enderecos', 'modelo' => 'grande', 'ids' => [$detalhe['id']]]) }}">Etiqueta QR</a>
                        <a class="me-btn" href="{{ \App\Filament\Resources\StockLocationResource::getUrl('edit', ['record' => $detalhe['id']]) }}">Editar endereço e itens</a>
                    </div>
                </div>
                @if ($detalhe['itens']->isEmpty())
                    <p class="odb-empty">Endereço vazio. Coloque itens nele em "Editar endereço e itens".</p>
                @else
                    <table>
                        <thead><tr><th>Item</th><th>Grupo</th><th class="n">Neste endereço</th><th class="n">Saldo total</th><th>Situação</th><th>Última contagem</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($detalhe['itens'] as $i)
                                <tr>
                                    <td><strong>{{ $i['nome'] }}</strong><br><small style="opacity:.65">{{ $i['tipo_rotulo'] }}@if ($i['sku']) · {{ $i['sku'] }}@endif</small></td>
                                    <td>{{ $i['grupo'] }}</td>
                                    <td class="n">{{ rtrim(rtrim(number_format($i['no_endereco'], 3, ',', '.'), '0'), ',') ?: '0' }} {{ $i['unidade'] }}</td>
                                    <td class="n">{{ rtrim(rtrim(number_format($i['quantidade'], 3, ',', '.'), '0'), ',') ?: '0' }}</td>
                                    <td>@if ($i['situacao'] === 'zerado')<span class="odb-pill crit">zerado</span>@elseif ($i['situacao'] === 'abaixo')<span class="odb-pill warn">abaixo do mínimo</span>@else<span class="odb-pill ok">normal</span>@endif</td>
                                    <td>@if ($i['ultima_contagem']){{ rtrim(rtrim(number_format((float) $i['ultima_contagem']->counted_quantity, 3, ',', '.'), '0'), ',') }} por {{ $i['ultima_contagem']->counted_by_name ?: '—' }}<br><small style="opacity:.65">{{ $i['ultima_contagem']->counted_at->format('d/m/Y H:i') }}</small>@else<small style="opacity:.65">nunca contado</small>@endif</td>
                                    <td><a class="me-btn" href="{{ \App\Filament\Pages\RastreioDoItemPage::getUrl(['item' => $i['tipo'].'|'.$i['id']]) }}">Rastrear</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
