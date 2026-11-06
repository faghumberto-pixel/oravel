<x-filament-panels::page>
    @include('dashboards.styles')

    <style>
        .ve-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .ve-chip { display: inline-flex; align-items: center; gap: 8px; padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid var(--odb-line, rgba(255,255,255,.14)); background: transparent; color: inherit; }
        .ve-chip i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
        .ve-chip small { opacity: .65; font-weight: 600; }
        .ve-chip.on { background: var(--odb-accent, #f97316); border-color: var(--odb-accent, #f97316); color: #fff; }
        .ve-filtros { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; align-items: end; }
        .ve-filtros label { font-size: 11px; font-weight: 700; opacity: .7; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 4px; }
        .ve-filtros select, .ve-filtros input { width: 100%; padding: 8px 10px; border-radius: 8px; font-size: 13px; border: 1px solid var(--odb-line, rgba(255,255,255,.18)); background: transparent; color: inherit; }
        .ve-filtros select option { color: #111; }
        .ve-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 700; border: 1px solid var(--odb-line, rgba(255,255,255,.18)); background: transparent; color: inherit; text-decoration: none; cursor: pointer; }
        .ve-btn.pri { background: var(--odb-accent, #f97316); border-color: var(--odb-accent, #f97316); color: #fff; }
        .ve-tab { width: 100%; border-collapse: collapse; font-size: 13px; }
        .ve-tab th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; opacity: .65; padding: 8px 10px; border-bottom: 1px solid var(--odb-line, rgba(255,255,255,.14)); }
        .ve-tab td { padding: 9px 10px; border-bottom: 1px solid var(--odb-line, rgba(255,255,255,.07)); vertical-align: top; }
        .ve-tab .n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .ve-tab small { display: block; opacity: .65; font-size: 11px; }
        .ve-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; }
        .ve-pag { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; font-size: 13px; }
    </style>

    <div class="odb">
        <p class="odb-lead">Tudo o que a empresa tem em estoque, num lugar só: materiais, peças e óleo, e itens agregados. Filtre por grupo (administração, equipamentos, veículos, manutenção, logística...), categoria, almoxarifado, corredor e situação.</p>

        {{-- Grupos --}}
        <div class="ve-chips" style="margin-bottom: 14px">
            <button type="button" wire:click="escolherGrupo(null)" @class(['ve-chip', 'on' => $grupo === null])>Todos <small>{{ array_sum($contagemPorGrupo->all()) }}</small></button>
            @foreach ($grupos as $g)
                <button type="button" wire:click="escolherGrupo('{{ $g->id }}')" @class(['ve-chip', 'on' => $grupo === (string) $g->id])><i style="background: {{ $g->color ?: '#94a3b8' }}"></i>{{ $g->name }} <small>{{ $contagemPorGrupo[(string) $g->id] ?? 0 }}</small></button>
            @endforeach
            @if ($semGrupo > 0)
                <button type="button" wire:click="escolherGrupo('sem')" @class(['ve-chip', 'on' => $grupo === 'sem'])><i style="background:#64748b"></i>Sem grupo <small>{{ $semGrupo }}</small></button>
            @endif
        </div>

        {{-- Filtros --}}
        <section class="odb-w" style="--span: 12; margin-bottom: 14px">
            <div class="ve-filtros">
                <div><label>Buscar</label><input type="search" wire:model.live.debounce.300ms="busca" placeholder="Nome ou código do item"></div>
                <div><label>Tipo de cadastro</label>
                    <select wire:model.live="tipo"><option value="">Todos</option><option value="material">Materiais</option><option value="part">Peças e óleo</option><option value="aggregate">Itens agregados</option></select></div>
                <div><label>Categoria</label>
                    <select wire:model.live="categoria"><option value="">Todas</option>@foreach ($opcoes['categorias'] as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></div>
                <div><label>Almoxarifado</label>
                    <select wire:model.live="unidade"><option value="">Todos</option>@foreach ($opcoes['unidades'] as $id => $nome)<option value="{{ $id }}">{{ $nome }}</option>@endforeach</select></div>
                <div><label>Corredor</label>
                    <select wire:model.live="corredor"><option value="">Todos</option>@foreach ($opcoes['corredores'] as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></div>
                <div><label>Situação</label>
                    <select wire:model.live="situacao"><option value="">Todas</option><option value="ok">Normal</option><option value="abaixo">Abaixo do mínimo</option><option value="zerado">Zerado</option></select></div>
                <div><label>Endereço</label>
                    <select wire:model.live="endereco"><option value="">Todos</option><option value="sem">Sem endereço</option></select></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="button" class="ve-btn" wire:click="limpar">Limpar filtros</button>
                    <a class="ve-btn pri" href="{{ $urlEtiquetas }}" target="_blank">Etiquetas QR</a>
                </div>
            </div>
        </section>

        <div class="odb-grid">
            {{-- Indicadores --}}
            @foreach ([
                ['Itens', number_format($resumo['itens'], 0, ',', '.'), $resumo['por_tipo']->map(fn ($n, $t) => $n.' '.mb_strtolower($t))->implode(' · ') ?: 'nenhum item', 'n'],
                ['Valor em estoque', 'R$ '.number_format($resumo['valor'], 2, ',', '.'), 'saldo × custo dos itens filtrados', 'n'],
                ['Abaixo do mínimo', $resumo['abaixo'], $resumo['zerados'].' zerado(s)', $resumo['abaixo'] + $resumo['zerados'] > 0 ? 'dn' : 'n'],
                ['Sem endereço', $resumo['sem_endereco'], 'ainda sem lugar na prateleira', $resumo['sem_endereco'] > 0 ? 'dn' : 'n'],
            ] as [$rotulo, $valor, $detalhe, $tom])
                <section class="odb-w" style="--span: 3">
                    <div class="odb-wh"><h3>{{ $rotulo }}</h3></div>
                    <div class="odb-kpi">{{ $valor }}</div>
                    <div class="odb-dl {{ $tom }}">{{ $detalhe }}</div>
                </section>
            @endforeach

            <section class="odb-w" style="--span: 6">
                <div class="odb-wh"><h3>Valor por grupo<small>onde está o dinheiro do estoque</small></h3></div>
                @include('dashboards.types.donut', ['p' => $donut])
            </section>
            <section class="odb-w" style="--span: 6">
                <div class="odb-wh"><h3>Categorias de maior valor</h3></div>
                @include('dashboards.types.ranking', ['p' => $ranking])
            </section>

            {{-- Lista --}}
            <section class="odb-w" style="--span: 12">
                <div class="odb-wh"><h3>Itens<small>{{ $total }} resultado(s). Use "Rastrear" para ver a movimentação e o uso de cada item.</small></h3></div>
                @if ($itens->isEmpty())
                    <p class="odb-empty">Nenhum item para esses filtros.</p>
                @else
                    <div style="overflow-x:auto">
                        <table class="ve-tab">
                            <thead><tr><th>Item</th><th>Grupo</th><th>Categoria</th><th class="n">Saldo</th><th class="n">Mínimo</th><th class="n">Valor</th><th>Situação</th><th>Endereço</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($itens as $i)
                                    <tr wire:key="{{ $i['tipo'] }}-{{ $i['id'] }}">
                                        <td><strong>{{ $i['nome'] }}</strong><small>{{ $i['tipo_rotulo'] }}@if ($i['sku']) · {{ $i['sku'] }}@endif</small></td>
                                        <td><span class="ve-dot" style="background: {{ $i['grupo_cor'] }}"></span>{{ $i['grupo'] }}</td>
                                        <td>{{ $i['categoria'] }}</td>
                                        <td class="n">{{ rtrim(rtrim(number_format($i['quantidade'], 3, ',', '.'), '0'), ',') ?: '0' }} {{ $i['unidade'] }}</td>
                                        <td class="n">{{ $i['minimo'] > 0 ? rtrim(rtrim(number_format($i['minimo'], 3, ',', '.'), '0'), ',') : '—' }}</td>
                                        <td class="n">R$ {{ number_format($i['valor'], 2, ',', '.') }}</td>
                                        <td>
                                            @if ($i['situacao'] === 'zerado')<span class="odb-pill crit">zerado</span>
                                            @elseif ($i['situacao'] === 'abaixo')<span class="odb-pill warn">abaixo do mínimo</span>
                                            @else<span class="odb-pill ok">normal</span>@endif
                                        </td>
                                        <td>@if ($i['sem_endereco'])<span class="odb-pill warn">sem endereço</span>@else{{ implode(' | ', array_slice($i['enderecos'], 0, 2)) }}@if (count($i['enderecos']) > 2) <small>+{{ count($i['enderecos']) - 2 }}</small>@endif @if ($i['nao_alocado'] > 0.0005)<small style="color:var(--odb-warn)">{{ rtrim(rtrim(number_format($i['nao_alocado'], 3, ',', '.'), '0'), ',') }} {{ $i['unidade'] }} ainda sem endereço</small>@endif @endif</td>
                                        <td><a class="ve-btn" href="{{ \App\Filament\Pages\RastreioDoItemPage::getUrl(['item' => $i['tipo'].'|'.$i['id']]) }}">Rastrear</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="ve-pag">
                        <button type="button" class="ve-btn" wire:click="mudarPagina(-1)" @disabled($pagina <= 1)>← Anterior</button>
                        <span>Página {{ $pagina }} de {{ $paginas }}</span>
                        <button type="button" class="ve-btn" wire:click="mudarPagina(1)" @disabled($pagina >= $paginas)>Próxima →</button>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
