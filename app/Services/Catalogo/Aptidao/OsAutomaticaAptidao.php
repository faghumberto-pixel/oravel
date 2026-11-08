<?php

namespace App\Services\Catalogo\Aptidao;

use App\Models\Asset;
use App\Models\CatalogoCumprimento;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Facades\Log;

/**
 * Item IMP reprovado (com gera_os) abre UMA OS corretiva para o ativo. Dedupe: se ja ha OS aberta para o mesmo requisito
 * neste ativo, o novo registro e vinculado a ela (nao abre outra). Fechar a OS NAO aprova o item: o ativo continua Inapto
 * ate um novo checklist OK (reinspecao) -- evita liberar equipamento so porque a OS foi encerrada.
 */
class OsAutomaticaAptidao
{
    private const STATUS_FECHADOS = ['Concluída', 'Completado', 'Cancelado', 'Cancelada'];

    public function gerar(CatalogoCumprimento $c): ?MaintenanceOrder
    {
        $snap = (array) $c->snapshot;

        if ($c->resultado !== CatalogoCumprimento::REPROVADO || ($snap['efeito'] ?? null) !== 'IMP' || empty($snap['gera_os']) || ! $c->requisito_id || $c->maintenance_order_id) {
            return null;
        }

        $aberta = CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $c->asset_id)->where('requisito_id', $c->requisito_id)
            ->whereNotNull('maintenance_order_id')->orderByDesc('realizado_em')->get()
            ->map(fn ($x) => MaintenanceOrder::withoutGlobalScopes()->find($x->maintenance_order_id))
            ->first(fn ($os) => $os && ! in_array($os->status, self::STATUS_FECHADOS, true));

        if (! $aberta) {
            $ativo = Asset::withoutGlobalScopes()->find($c->asset_id);

            $aberta = MaintenanceOrder::create([
                'tenant_id' => $c->tenant_id, 'asset_id' => $c->asset_id, 'client_id' => $ativo?->client_id,
                'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Aberto', 'origin' => 'aptidao_auto',
                'description' => 'Checklist do grupo: item "'.($snap['nome'] ?? $snap['slug'] ?? 'impeditivo').'" reprovado (impeditivo — o ativo fica Inapto até nova inspeção).'
                    .($c->observacao ? "\nObservação: {$c->observacao}" : ''),
            ]);
        }

        CatalogoCumprimento::withoutGlobalScopes()->whereKey($c->id)->update(['maintenance_order_id' => $aberta->id]);
        $c->maintenance_order_id = $aberta->id;

        return $aberta;
    }

    /** Nunca derruba o registro do checklist: falha de OS e logada e o cumprimento fica gravado. */
    public function tentar(CatalogoCumprimento $c): void
    {
        try {
            if (AptidaoGate::ligada($c->tenant_id)) {
                $this->gerar($c);
            }
        } catch (\Throwable $e) {
            Log::error('G1 aptidao: falha ao abrir OS automatica', ['cumprimento_id' => $c->id, 'erro' => $e->getMessage()]);
        }
    }
}
