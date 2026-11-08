<?php

namespace App\Services\Checklists;

use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\CkExecution;
use App\Models\CkItem;
use App\Models\CkSection;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * O que a tela da OS mostra sobre os checklists por grupo: os modelos que valem para o ativo (pela regra do grupo de checklist, ver
 * ChecklistAplicavel), de onde vem cada vinculo, e os ultimos checklists feitos nesse equipamento. Devolve nulo quando a pessoa nao
 * pode ver nem executar (a secao some, sem aviso). So le.
 */
class ChecklistsDaOs
{
    public function __construct(private ChecklistAplicavel $aplicavel) {}

    /** @return array{ativo: Asset, modelos: array<int, array{template: CkTemplate, itens: int, criticos: int, motivo: string}>, ultimas: Collection, pode_executar: bool}|null */
    public function paraOrdemServico(MaintenanceOrder $os): ?array
    {
        return $this->paraAtivo($os->asset_id);
    }

    /** @return array{ativo: Asset, modelos: array<int, array{template: CkTemplate, itens: int, criticos: int, motivo: string}>, ultimas: Collection, pode_executar: bool}|null */
    public function paraAtivo(?string $assetId): ?array
    {
        $podeExecutar = Gate::allows('create', CkExecution::class);

        if (! $assetId || ! (Gate::allows('viewAny', CkExecution::class) || $podeExecutar)) {
            return null;
        }

        $ativo = Asset::find($assetId);

        if (! $ativo) {
            return null;
        }

        $modelos = $this->aplicavel->paraAtivo($ativo)->map(function (CkTemplate $t) use ($ativo) {
            $secoes = CkSection::withoutGlobalScopes()->where('template_id', $t->id)->whereNull('deleted_at')->pluck('id');
            $itens = CkItem::withoutGlobalScopes()->whereIn('section_id', $secoes)->where('ativo', true)->whereNull('deleted_at');

            return [
                'template' => $t, 'itens' => (clone $itens)->count(), 'criticos' => (clone $itens)->where('critico', true)->count(),
                'motivo' => $this->motivo($t, $ativo),
            ];
        })->all();

        $ultimas = CkExecution::query()->where('asset_id', $ativo->id)->orderByDesc('finalizado_em')->limit(5)->get();

        return ['ativo' => $ativo, 'modelos' => $modelos, 'ultimas' => $ultimas, 'pode_executar' => $podeExecutar];
    }

    private function motivo(CkTemplate $t, Asset $ativo): string
    {
        if ($t->checklist_group_id && $t->checklist_group_id === $ativo->checklist_group_id) {
            $nome = ChecklistGroup::withoutGlobalScopes()->where('tenant_id', $ativo->tenant_id)->whereKey($t->checklist_group_id)->value('name');

            return 'grupo de checklist: '.($nome ?? '—');
        }

        return 'grupo de equipamento: '.($t->grupo?->name ?? '—');
    }
}
