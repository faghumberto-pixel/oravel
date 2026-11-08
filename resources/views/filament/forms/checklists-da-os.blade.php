@php
    $cores = ['aprovado' => 'success', 'aprovado_com_ressalva' => 'warning', 'reprovado' => 'danger'];
    $rotulos = \App\Support\CkExecucaoExport::STATUS;
@endphp
<div style="display:flex;flex-direction:column;gap:.75rem" data-testid="checklists-da-os">
    @if (($dados['pendentes'] ?? 0) > 0)
        <div style="padding:.5rem .75rem;border-radius:.5rem;background:rgba(245,158,11,.15);font-weight:600" data-testid="aviso-checklist-pendente">
            ⚠ {{ $dados['pendentes'] === 1 ? 'Há 1 checklist' : 'Há '.$dados['pendentes'].' checklists' }} do equipamento ainda não {{ $dados['pendentes'] === 1 ? 'feito' : 'feitos' }} nesta OS. No Modo Campo a OS só conclui depois de feitos.
        </div>
    @endif
    @forelse ($dados['modelos'] as $m)
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;padding-bottom:.5rem;border-bottom:1px solid rgba(128,128,128,.25)">
            <div>
                <div style="font-weight:600">
                    @if ($dados['pode_executar'])
                        <x-filament::link :href="route('ck.execucao.desktop', array_filter(['assetId' => $dados['ativo']->id, 'checklist' => $m['template']->id, 'os' => $os->id ?? null]))" target="_blank">{{ $m['template']->nome }}</x-filament::link>
                    @else
                        {{ $m['template']->nome }}
                    @endif
                </div>
                <div style="opacity:.75;font-size:.8rem">{{ $m['itens'] }} itens · {{ $m['criticos'] }} críticos · {{ $m['motivo'] }}</div>
                @if (! empty($m['secoes']))
                    <details style="margin-top:.35rem;font-size:.85rem" data-testid="itens-do-checklist">
                        <summary style="cursor:pointer;font-weight:600">Ver os {{ $m['itens'] }} itens a conferir</summary>
                        @foreach ($m['secoes'] as $secao)
                            <div style="margin-top:.4rem;font-weight:600">{{ $secao['titulo'] }}</div>
                            <ul style="margin:.1rem 0 0 1.1rem;list-style:disc">
                                @foreach ($secao['itens'] as $item)
                                    <li>{{ $item['descricao'] }}@if ($item['critico']) <strong style="color:#b91c1c">· crítico</strong>@endif</li>
                                @endforeach
                            </ul>
                        @endforeach
                    </details>
                @endif
                @if (! empty($os))
                    @if ($m['feito'])
                        <div style="font-size:.8rem;font-weight:600;color:#059669">
                            @if (\App\Filament\Resources\CkExecutionResource::canView($m['feito']))
                                <a href="{{ \App\Filament\Resources\CkExecutionResource::getUrl('view', ['record' => $m['feito']]) }}" target="_blank" style="color:inherit;text-decoration:underline">✔ Feito em {{ $m['feito']->finalizado_em?->format('d/m/Y H:i') }}{{ $m['feito']->executor?->name ? ' por '.$m['feito']->executor->name : '' }} · {{ \App\Support\CkExecucaoExport::STATUS[$m['feito']->status] ?? $m['feito']->status }}</a>
                            @else
                                ✔ Feito em {{ $m['feito']->finalizado_em?->format('d/m/Y H:i') }}{{ $m['feito']->executor?->name ? ' por '.$m['feito']->executor->name : '' }} · {{ \App\Support\CkExecucaoExport::STATUS[$m['feito']->status] ?? $m['feito']->status }}
                            @endif
                        </div>
                    @else
                        <div style="font-size:.8rem;font-weight:600;color:#b45309">⚠ Pendente nesta OS</div>
                    @endif
                @endif
            </div>
            @if ($dados['pode_executar'])
                <x-filament::link :href="route('ck.execucao.desktop', array_filter(['assetId' => $dados['ativo']->id, 'checklist' => $m['template']->id, 'os' => $os->id ?? null]))" target="_blank" icon="heroicon-m-clipboard-document-check">{{ ! empty($os) && $m['feito'] ? 'Refazer' : 'Executar' }}</x-filament::link>
            @endif
        </div>
    @empty
        <p style="opacity:.8">Nenhum checklist por grupo se aplica a este equipamento. Ligue um checklist ao grupo de checklist ou ao grupo de equipamento dele em Manutenção › Cadastros › Checklists de equipamento.</p>
    @endforelse

    @if (! empty($os) && count($dados['modelos']) > 0)
        <div style="font-size:.85rem">
            <x-filament::link :href="route('maintenance-orders.folha', $os)" target="_blank" icon="heroicon-m-printer">Imprimir a folha da OS com estes checklists</x-filament::link>
        </div>
    @endif

    @if ($dados['ultimas']->isNotEmpty())
        <div>
            <div style="font-weight:600;margin-bottom:.25rem">Últimos checklists deste equipamento</div>
            @foreach ($dados['ultimas'] as $e)
                <div style="display:flex;justify-content:space-between;gap:1rem;font-size:.85rem;padding:.15rem 0">
                    @if (\App\Filament\Resources\CkExecutionResource::canView($e))
                        <a href="{{ \App\Filament\Resources\CkExecutionResource::getUrl('view', ['record' => $e]) }}" target="_blank" style="text-decoration:underline">{{ $e->finalizado_em?->format('d/m/Y H:i') }} · {{ $e->template_nome }}</a>
                    @else
                        <span>{{ $e->finalizado_em?->format('d/m/Y H:i') }} · {{ $e->template_nome }}</span>
                    @endif
                    <x-filament::badge :color="$cores[$e->status] ?? 'gray'" size="sm">{{ $rotulos[$e->status] ?? $e->status }}</x-filament::badge>
                </div>
            @endforeach
        </div>
    @endif
</div>
