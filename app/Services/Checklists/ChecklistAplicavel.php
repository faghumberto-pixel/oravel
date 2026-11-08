<?php

namespace App\Services\Checklists;

use App\Models\Asset;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Quais checklists valem para um ativo (e, por consequencia, para a OS dele).
 *
 * Regra: o que ativa o checklist na OS e o GRUPO DE CHECKLIST do ativo (assets.checklist_group_id).
 *  1. Template ligado a um grupo de checklist vale para os ativos DESSE grupo.
 *  2. Template sem grupo de checklist vale para todos os ativos do mesmo GRUPO DE EQUIPAMENTO (catalogo G1) -- e assim os
 *     templates padrao ja servem em empresas que ainda nao montaram grupos de checklist.
 * So entram templates ativos e nao arquivados, do tenant do ativo.
 */
class ChecklistAplicavel
{
    /**
     * @param  array<int, string>|null  $tipos  restringe por tipo (ex.: ['saida', 'entrada'])
     * @return Collection<int, CkTemplate>
     */
    public function paraAtivo(Asset $ativo, ?array $tipos = null): Collection
    {
        $grupoChecklist = $ativo->checklist_group_id;
        $grupoEquipamento = $ativo->asset_category_id;

        if (! $grupoChecklist && ! $grupoEquipamento) {
            return collect();
        }

        return CkTemplate::withoutGlobalScopes()
            ->where('tenant_id', $ativo->tenant_id)->where('ativo', true)->whereNull('deleted_at')
            ->where(function ($q) use ($grupoChecklist, $grupoEquipamento) {
                if ($grupoChecklist) {
                    $q->orWhere('checklist_group_id', $grupoChecklist);
                }

                if ($grupoEquipamento) {
                    $q->orWhere(fn ($w) => $w->whereNull('checklist_group_id')->where('grupo_id', $grupoEquipamento));
                }
            })
            ->when($tipos, fn ($q) => $q->whereIn('tipo', $tipos))
            ->orderBy('nome')->get();
    }

    /** @return Collection<int, CkTemplate> */
    public function paraOrdemServico(MaintenanceOrder $os, ?array $tipos = null): Collection
    {
        $ativo = $os->asset_id ? Asset::withoutGlobalScopes()->where('tenant_id', $os->tenant_id)->find($os->asset_id) : null;

        return $ativo ? $this->paraAtivo($ativo, $tipos) : collect();
    }

    /**
     * Mesma regra de paraAtivo(), em SQL, para filtrar muitos ativos de uma vez: ativos COM (ou, com $com = false, SEM) pelo menos um checklist aplicavel.
     * (Mexeu em uma regra, mexa na outra: ChecklistsVisaoGeralTest garante que as duas concordam.)
     *
     * @param  Builder<Asset>  $ativos
     * @return Builder<Asset>
     */
    public function filtrarAtivosComChecklist(Builder $ativos, bool $com = true): Builder
    {
        $existe = function ($q) {
            $q->selectRaw('1')->from('ck_templates')
                ->whereColumn('ck_templates.tenant_id', 'assets.tenant_id')->where('ck_templates.ativo', true)->whereNull('ck_templates.deleted_at')
                ->where(function ($w) {
                    $w->where(fn ($x) => $x->whereNotNull('ck_templates.checklist_group_id')->whereColumn('ck_templates.checklist_group_id', 'assets.checklist_group_id'))
                        ->orWhere(fn ($x) => $x->whereNull('ck_templates.checklist_group_id')->whereNotNull('assets.asset_category_id')->whereColumn('ck_templates.grupo_id', 'assets.asset_category_id'));
                });
        };

        return $com ? $ativos->whereExists($existe) : $ativos->whereNotExists($existe);
    }
}
