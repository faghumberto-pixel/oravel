@php
    $cores = ['aprovado' => 'success', 'aprovado_com_ressalva' => 'warning', 'reprovado' => 'danger'];
    $rotulos = \App\Support\CkExecucaoExport::STATUS;
@endphp
<div style="display:flex;flex-direction:column;gap:.75rem" data-testid="checklists-da-os">
    @forelse ($dados['modelos'] as $m)
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;padding-bottom:.5rem;border-bottom:1px solid rgba(128,128,128,.25)">
            <div>
                <div style="font-weight:600">{{ $m['template']->nome }}</div>
                <div style="opacity:.75;font-size:.8rem">{{ $m['itens'] }} itens · {{ $m['criticos'] }} críticos · {{ $m['motivo'] }}</div>
            </div>
            @if ($dados['pode_executar'])
                <x-filament::link :href="route('ck.execucao.mobile', ['assetId' => $dados['ativo']->id, 'checklist' => $m['template']->id])" target="_blank" icon="heroicon-m-clipboard-document-check">Executar</x-filament::link>
            @endif
        </div>
    @empty
        <p style="opacity:.8">Nenhum checklist por grupo se aplica a este equipamento. Ligue um checklist ao grupo de checklist ou ao grupo de equipamento dele em Manutenção › Cadastros › Checklists de equipamento.</p>
    @endforelse

    @if ($dados['ultimas']->isNotEmpty())
        <div>
            <div style="font-weight:600;margin-bottom:.25rem">Últimos checklists deste equipamento</div>
            @foreach ($dados['ultimas'] as $e)
                <div style="display:flex;justify-content:space-between;gap:1rem;font-size:.85rem;padding:.15rem 0">
                    <span>{{ $e->finalizado_em?->format('d/m/Y H:i') }} · {{ $e->template_nome }}</span>
                    <x-filament::badge :color="$cores[$e->status] ?? 'gray'" size="sm">{{ $rotulos[$e->status] ?? $e->status }}</x-filament::badge>
                </div>
            @endforeach
        </div>
    @endif
</div>
