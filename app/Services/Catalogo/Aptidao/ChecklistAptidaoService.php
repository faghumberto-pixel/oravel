<?php

namespace App\Services\Catalogo\Aptidao;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoCumprimento;
use App\Models\CatalogoRequisito;
use App\Models\MaintenanceOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Checklist do GRUPO do ativo (itens IMP/ALR), usado pelo app do tecnico. Cada resposta vira um cumprimento (historico com snapshot);
 * IMP reprovado com gera_os abre a OS pelo gatilho do CatalogoCumprimento. Documentos e inspecoes NAO passam por aqui
 * (exigem validade/anexo e permissao de gestao): so checklist.
 */
class ChecklistAptidaoService
{
    public function __construct(private AssetFitnessService $motor) {}

    /** @return Collection<int, CatalogoRequisito> */
    public function itens(Asset $ativo): Collection
    {
        $grupo = $ativo->asset_category_id ? AssetCategory::withoutGlobalScopes()->where('tenant_id', $ativo->tenant_id)->whereNull('deleted_at')->find($ativo->asset_category_id) : null;

        if (! $grupo) {
            return collect();
        }

        return CatalogoRequisito::withoutGlobalScopes()->with(['item' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('tenant_id', $ativo->tenant_id)->where('grupo_id', $grupo->id)->whereIn('efeito', ['IMP', 'ALR'])
            ->where('ativo', true)->whereNull('deleted_at')->orderByRaw("case efeito when 'IMP' then 0 else 1 end")->orderBy('ordem')->get();
    }

    /**
     * @param  array<string, array{resultado?: string, observacao?: ?string}>  $respostas  indexado por requisito_id
     * @return array{registrados: int, reprovados: array<int, string>, os: array<int, string>, status: string}
     *
     * @throws ValidationException
     */
    public function registrar(Asset $ativo, array $respostas, ?User $usuario = null, string $origem = 'campo'): array
    {
        $permitidos = $this->itens($ativo)->keyBy('id');
        $respostas = array_filter($respostas, fn ($r) => in_array($r['resultado'] ?? null, [CatalogoCumprimento::OK, CatalogoCumprimento::REPROVADO], true));

        if ($respostas === []) {
            throw ValidationException::withMessages(['respostas' => 'Marque OK ou Reprovado em pelo menos um item.']);
        }

        foreach (array_keys($respostas) as $requisitoId) {
            if (! $permitidos->has($requisitoId)) {
                throw ValidationException::withMessages(['respostas' => 'Há item que não pertence ao checklist do grupo deste ativo.']);
            }
        }

        $usuario ??= auth()->user();
        $reprovados = [];
        $os = [];

        DB::transaction(function () use ($ativo, $respostas, $permitidos, $usuario, $origem, &$reprovados, &$os) {
            foreach ($respostas as $requisitoId => $r) {
                $c = CatalogoCumprimento::create([
                    'tenant_id' => $ativo->tenant_id, 'asset_id' => $ativo->id, 'requisito_id' => $requisitoId,
                    'resultado' => $r['resultado'], 'realizado_em' => now(), 'observacao' => filled($r['observacao'] ?? null) ? trim($r['observacao']) : null,
                    'origem' => $origem, 'registrado_por' => $usuario?->id,
                ]);

                if ($c->resultado === CatalogoCumprimento::REPROVADO) {
                    $reprovados[] = $permitidos[$requisitoId]->item?->nome ?? $permitidos[$requisitoId]->slug;

                    if ($c->maintenance_order_id && ($numero = MaintenanceOrder::withoutGlobalScopes()->find($c->maintenance_order_id)?->os_number)) {
                        $os[$c->maintenance_order_id] = (string) $numero;
                    }
                }
            }
        });

        $status = $this->motor->recalcular($ativo)->status;

        return ['registrados' => count($respostas), 'reprovados' => $reprovados, 'os' => array_values($os), 'status' => $status];
    }
}
