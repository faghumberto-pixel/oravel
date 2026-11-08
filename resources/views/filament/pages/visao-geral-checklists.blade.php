@php
    $svc = $this->servico();
    $f = $this->filtros();
    $aba = $this->aba;
    $rotStatus = \App\Services\Checklists\ChecklistVisaoGeral::STATUS_COBERTURA;
    $corStatus = ['sem_checklist' => 'danger', 'nunca' => 'warning', 'atrasado' => 'warning', 'em_dia' => 'success'];
    $rotResp = ['conforme' => 'Conforme', 'nao_conforme' => 'NÃO CONFORME', 'na' => 'Não se aplica'];
    $corResp = ['conforme' => 'success', 'nao_conforme' => 'danger', 'na' => 'gray'];
    $corRes = ['aprovado' => 'success', 'aprovado_com_ressalva' => 'warning', 'reprovado' => 'danger'];
    $rotRes = \App\Support\CkExecucaoExport::STATUS;
    $rotDiag = ['sem_checklist' => 'Sem checklist', 'nunca_conferido' => 'Nunca conferido', 'ja_registrado' => 'Já estava registrado', 'conferido' => 'Conferido sem problema', 'sem_item' => 'Sem item no checklist'];
    $corDiag = ['sem_checklist' => 'gray', 'nunca_conferido' => 'warning', 'ja_registrado' => 'danger', 'conferido' => 'info', 'sem_item' => 'warning'];
    $verExec = fn ($e) => \App\Filament\Resources\CkExecutionResource::canView($e) ? \App\Filament\Resources\CkExecutionResource::getUrl('view', ['record' => $e]) : null;
@endphp
<x-filament-panels::page>
    <style>
        .ckvg .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: .75rem; }
        .ckvg .card { text-align: left; border: 1px solid rgba(128,128,128,.3); border-radius: .75rem; padding: .75rem 1rem; background: transparent; cursor: pointer; color: inherit; font: inherit; }
        .ckvg .card:hover { border-color: rgba(245,158,11,.9); }
        .ckvg .card.on { border-color: #f59e0b; box-shadow: 0 0 0 2px rgba(245,158,11,.35); }
        .ckvg .card .n { font-size: 1.6rem; font-weight: 800; line-height: 1.1; }
        .ckvg .card .l { font-size: .75rem; opacity: .8; }
        .ckvg .filtros { display: flex; flex-wrap: wrap; gap: .75rem 1rem; align-items: end; }
        .ckvg .filtros label { display: flex; flex-direction: column; gap: .2rem; font-size: .75rem; font-weight: 600; opacity: .85; }
        .ckvg table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .ckvg th { text-align: left; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; opacity: .7; padding: .4rem .5rem; border-bottom: 2px solid rgba(128,128,128,.3); }
        .ckvg td { padding: .55rem .5rem; border-top: 1px solid rgba(128,128,128,.2); vertical-align: top; }
        .ckvg a.l { text-decoration: underline; font-weight: 600; }
        .ckvg .peq { font-size: .75rem; opacity: .75; }
        .ckvg .rel { margin-top: .35rem; padding: .4rem .6rem; border-radius: .5rem; background: rgba(128,128,128,.1); font-size: .8rem; }
        .ckvg .vazio { padding: 1.5rem; text-align: center; opacity: .7; }
        .ckvg .ajuda { font-size: .8rem; opacity: .8; margin-bottom: .75rem; }
    </style>

    <div class="ckvg" style="display:flex;flex-direction:column;gap:1rem" data-testid="visao-geral-checklists">
        <x-filament::section>
            <div class="filtros">
                <label>De<x-filament::input.wrapper><x-filament::input type="date" wire:model.live="de" /></x-filament::input.wrapper></label>
                <label>Até<x-filament::input.wrapper><x-filament::input type="date" wire:model.live="ate" /></x-filament::input.wrapper></label>
                <label>Grupo de equipamento
                    <x-filament::input.wrapper><x-filament::input.select wire:model.live="grupo"><option value="">Todos</option>@foreach ($this->grupos() as $id => $nome)<option value="{{ $id }}">{{ $nome }}</option>@endforeach</x-filament::input.select></x-filament::input.wrapper>
                </label>
                <label>Atrasado após (dias)<x-filament::input.wrapper><x-filament::input type="number" min="1" wire:model.live.debounce.600ms="dias" style="width:6rem" /></x-filament::input.wrapper></label>
                <x-filament::button color="gray" size="sm" wire:click="limparFiltros">Limpar filtros</x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::tabs>
            <x-filament::tabs.item :active="$aba === 'cobertura'" wire:click="irParaAba('cobertura')" icon="heroicon-m-squares-2x2">Ativos e checklists</x-filament::tabs.item>
            <x-filament::tabs.item :active="$aba === 'servicos'" wire:click="irParaAba('servicos')" icon="heroicon-m-wrench-screwdriver">Serviços (OS)</x-filament::tabs.item>
            <x-filament::tabs.item :active="$aba === 'corretivas'" wire:click="irParaAba('corretivas')" icon="heroicon-m-exclamation-triangle">Corretivas × checklist</x-filament::tabs.item>
        </x-filament::tabs>

        {{-- ============================== 1. COBERTURA ============================== --}}
        @if ($aba === 'cobertura')
            @php $r = $svc->resumoCobertura($f); @endphp
            <p class="ajuda">Quais ativos têm checklist e quais não têm. Clique num cartão para filtrar a lista; clique de novo para limpar.</p>
            <div class="cards">
                <button type="button" class="card {{ $this->status === null ? 'on' : '' }}" wire:click="filtrarCobertura(null)"><div class="n">{{ $r['total'] }}</div><div class="l">Ativos</div></button>
                @foreach ($rotStatus as $chave => $rotulo)
                    <button type="button" class="card {{ $this->status === $chave ? 'on' : '' }}" wire:click="filtrarCobertura('{{ $chave }}')"><div class="n">{{ $r[$chave] }}</div><div class="l">{{ $rotulo }}{{ $chave === 'atrasado' ? ' (> '.$this->dias.' dias)' : '' }}</div></button>
                @endforeach
            </div>
            @php $linhas = $svc->ativos($f); @endphp
            <x-filament::section>
                @if ($linhas->isEmpty())
                    <div class="vazio">Nenhum ativo neste filtro.</div>
                @else
                    <div style="overflow-x:auto"><table>
                        <thead><tr><th>Ativo</th><th>Grupo</th><th>Checklists aplicáveis</th><th>Situação</th><th>Último checklist</th><th>OS no período</th><th>OS com checklist</th></tr></thead>
                        <tbody>
                        @foreach ($linhas as $l)
                            <tr wire:key="cob-{{ $l['ativo']->id }}">
                                <td><a class="l" href="{{ \App\Filament\Resources\AssetResource::getUrl('edit', ['record' => $l['ativo']]) }}">{{ $l['ativo']->name }}</a><div class="peq">{{ $l['ativo']->patrimonio ?: $l['ativo']->tag }}</div></td>
                                <td>{{ $l['grupo'] ?? '—' }}</td>
                                <td>@forelse ($l['modelos'] as $m){{ $m }}@if (! $loop->last)<br>@endif @empty <span class="peq">nenhum</span> @endforelse</td>
                                <td><x-filament::badge :color="$corStatus[$l['status']]" size="sm">{{ $rotStatus[$l['status']] }}</x-filament::badge></td>
                                <td>
                                    @if ($l['ultimo'])
                                        @if ($verExec($l['ultimo']))<a class="l" href="{{ $verExec($l['ultimo']) }}">{{ $l['ultimo']->finalizado_em->format('d/m/Y H:i') }}</a>@else{{ $l['ultimo']->finalizado_em->format('d/m/Y H:i') }}@endif
                                        <div class="peq">há {{ $l['dias_desde'] }} {{ $l['dias_desde'] === 1 ? 'dia' : 'dias' }} · <x-filament::badge :color="$corRes[$l['ultimo']->status] ?? 'gray'" size="sm">{{ $rotRes[$l['ultimo']->status] ?? $l['ultimo']->status }}</x-filament::badge></div>
                                    @else — @endif
                                </td>
                                <td>{{ $l['os_no_periodo'] }}</td>
                                <td>{{ $l['os_com_checklist'] }}@if ($l['os_no_periodo'] > 0 && $l['modelos'] !== [] && $l['os_com_checklist'] < $l['os_no_periodo']) <span class="peq" style="color:#b45309">({{ $l['os_no_periodo'] - $l['os_com_checklist'] }} sem)</span>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    <div style="margin-top:.75rem"><x-filament::pagination :paginator="$linhas" /></div>
                @endif
            </x-filament::section>
        @endif

        {{-- ============================== 2. SERVICOS ============================== --}}
        @if ($aba === 'servicos')
            @php $r = $svc->resumoServicos($f); $rotSit = ['feito' => 'Checklist feito', 'pendente' => 'Checklist pendente', 'sem_modelo' => 'Sem checklist aplicável']; @endphp
            <p class="ajuda">Quais serviços foram feitos e se o checklist foi preenchido nesta OS. Clique num cartão para filtrar.</p>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end;margin-bottom:.25rem">
                <label style="display:flex;flex-direction:column;gap:.2rem;font-size:.75rem;font-weight:600;opacity:.85">Tipo de serviço
                    <x-filament::input.wrapper><x-filament::input.select wire:model.live="tipo"><option value="">Todos</option>@foreach (\App\Services\Checklists\ChecklistVisaoGeral::TIPOS_DE_SERVICO as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</x-filament::input.select></x-filament::input.wrapper>
                </label>
            </div>
            <div class="cards">
                <button type="button" class="card {{ $this->checklist === null ? 'on' : '' }}" wire:click="filtrarServicos(null)"><div class="n">{{ $r['total'] }}</div><div class="l">Serviços no período</div></button>
                @foreach (['feito' => 'Checklist feito', 'pendente' => 'Checklist pendente', 'sem_modelo' => 'Sem checklist aplicável'] as $chave => $rotulo)
                    <button type="button" class="card {{ $this->checklist === $chave ? 'on' : '' }}" wire:click="filtrarServicos('{{ $chave }}')"><div class="n">{{ $r[$chave] }}</div><div class="l">{{ $rotulo }}</div></button>
                @endforeach
            </div>
            @php $linhas = $svc->servicos($f); $corSit = ['feito' => 'success', 'pendente' => 'warning', 'sem_modelo' => 'gray']; @endphp
            <x-filament::section>
                @if ($linhas->isEmpty())
                    <div class="vazio">Nenhum serviço neste filtro.</div>
                @else
                    <div style="overflow-x:auto"><table>
                        <thead><tr><th>OS</th><th>Data</th><th>Tipo</th><th>Ativo</th><th>Checklist nesta OS</th><th>Feito por / resultado</th></tr></thead>
                        <tbody>
                        @foreach ($linhas as $l)
                            @php $os = $l['os']; $e = $l['feitos']->first(); @endphp
                            <tr wire:key="serv-{{ $os->id }}">
                                <td><a class="l" href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $os]) }}">{{ $os->os_number }}</a><div class="peq">{{ $os->status }}</div></td>
                                <td>{{ $os->created_at?->format('d/m/Y') }}</td>
                                <td>{{ $os->maintenance_type }}</td>
                                <td>@if ($l['ativo'])<a class="l" href="{{ \App\Filament\Resources\AssetResource::getUrl('edit', ['record' => $l['ativo']]) }}">{{ $l['ativo']->name }}</a>@else — @endif</td>
                                <td><x-filament::badge :color="$corSit[$l['situacao']]" size="sm">{{ $rotSit[$l['situacao']] }}</x-filament::badge>@if ($l['modelos'])<div class="peq">{{ implode(' · ', $l['modelos']) }}</div>@endif</td>
                                <td>
                                    @if ($e)
                                        @if ($verExec($e))<a class="l" href="{{ $verExec($e) }}">{{ $e->finalizado_em->format('d/m/Y H:i') }}</a>@else{{ $e->finalizado_em->format('d/m/Y H:i') }}@endif
                                        <div class="peq">{{ $e->executor?->name }} · <x-filament::badge :color="$corRes[$e->status] ?? 'gray'" size="sm">{{ $rotRes[$e->status] ?? $e->status }}</x-filament::badge></div>
                                    @else — @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    <div style="margin-top:.75rem"><x-filament::pagination :paginator="$linhas" /></div>
                @endif
            </x-filament::section>
        @endif

        {{-- ============================== 3. CORRETIVAS x CHECKLIST ============================== --}}
        @if ($aba === 'corretivas')
            @php $r = $svc->resumoCorretivas($f); @endphp
            <p class="ajuda">Para cada corretiva: o que o checklist dizia <strong>antes</strong> dela (última OS e último checklist) e se algum item tem relação com o problema. A relação é por palavras em comum (mostradas em cada linha) e serve de pista, não de sentença. Clique num cartão para filtrar.</p>
            <div class="cards">
                <button type="button" class="card {{ $this->diagnostico === null ? 'on' : '' }}" wire:click="filtrarCorretivas(null)"><div class="n">{{ $r['total'] }}</div><div class="l">Corretivas no período</div></button>
                @foreach ($rotDiag as $chave => $rotulo)
                    <button type="button" class="card {{ $this->diagnostico === $chave ? 'on' : '' }}" wire:click="filtrarCorretivas('{{ $chave }}')"><div class="n">{{ $r[$chave] }}</div><div class="l">{{ $rotulo }}</div></button>
                @endforeach
            </div>
            @php $linhas = $svc->corretivas($f, $this->diagnostico); @endphp
            <x-filament::section>
                @if ($linhas->isEmpty())
                    <div class="vazio">Nenhuma corretiva neste filtro.</div>
                @else
                    <div style="overflow-x:auto"><table>
                        <thead><tr><th>Corretiva</th><th>Última OS antes</th><th>Último checklist antes</th><th>Relação com o checklist</th></tr></thead>
                        <tbody>
                        @foreach ($linhas as $c)
                            @php $os = $c['os']; $ult = $c['ultimo_checklist']; @endphp
                            <tr wire:key="cor-{{ $os->id }}">
                                <td style="min-width:16rem">
                                    <a class="l" href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $os]) }}">{{ $os->os_number }}</a> · {{ $os->created_at?->format('d/m/Y') }}
                                    <div>@if ($c['ativo'])<a class="l" href="{{ \App\Filament\Resources\AssetResource::getUrl('edit', ['record' => $c['ativo']]) }}">{{ $c['ativo']->name }}</a>@endif</div>
                                    <div class="peq">{{ $c['problema'] ?: 'sem descrição do problema' }}</div>
                                </td>
                                <td style="min-width:12rem">
                                    @if ($c['ultima_os'])
                                        <a class="l" href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $c['ultima_os']]) }}">{{ $c['ultima_os']->os_number }}</a> · {{ $c['ultima_os']->maintenance_type }}
                                        <div class="peq">{{ $c['ultima_os']->created_at?->format('d/m/Y') }}</div>
                                        <div><x-filament::badge :color="$c['checklist_na_ultima_os'] ? 'success' : ($c['tinha_modelo'] ? 'warning' : 'gray')" size="sm">{{ $c['checklist_na_ultima_os'] ? 'Checklist feito nela' : ($c['tinha_modelo'] ? 'Checklist NÃO feito nela' : 'Sem checklist aplicável') }}</x-filament::badge></div>
                                    @else <span class="peq">sem OS anterior</span> @endif
                                </td>
                                <td style="min-width:12rem">
                                    @if ($ult)
                                        @if ($verExec($ult))<a class="l" href="{{ $verExec($ult) }}">{{ $ult->finalizado_em->format('d/m/Y H:i') }}</a>@else{{ $ult->finalizado_em->format('d/m/Y H:i') }}@endif
                                        <div class="peq">{{ $c['dias_antes'] }} {{ $c['dias_antes'] === 1 ? 'dia' : 'dias' }} antes · <x-filament::badge :color="$corRes[$ult->status] ?? 'gray'" size="sm">{{ $rotRes[$ult->status] ?? $ult->status }}</x-filament::badge></div>
                                    @else <span class="peq">nenhum</span> @endif
                                </td>
                                <td style="min-width:22rem">
                                    <x-filament::badge :color="$corDiag[$c['diagnostico']]">{{ $rotDiag[$c['diagnostico']] }}</x-filament::badge>
                                    <div class="peq" style="margin-top:.2rem">{{ $c['rotulo'] }}</div>
                                    @foreach (array_slice($c['relacionados'], 0, 4) as $rel)
                                        <div class="rel">
                                            <x-filament::badge :color="$corResp[$rel['resposta']] ?? 'gray'" size="sm">{{ $rotResp[$rel['resposta']] ?? '—' }}</x-filament::badge>
                                            {{ $rel['item'] }}@if ($rel['critico']) <strong style="color:#b91c1c">CRÍTICO</strong>@endif
                                            @if ($rel['observacao'])<div class="peq">“{{ $rel['observacao'] }}”</div>@endif
                                            <div class="peq">palavras em comum: {{ implode(', ', $rel['palavras']) }}</div>
                                        </div>
                                    @endforeach
                                    @if (count($c['relacionados']) > 4)<div class="peq">+ {{ count($c['relacionados']) - 4 }} itens relacionados (veja no Excel)</div>@endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    <div style="margin-top:.75rem"><x-filament::pagination :paginator="$linhas" /></div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
