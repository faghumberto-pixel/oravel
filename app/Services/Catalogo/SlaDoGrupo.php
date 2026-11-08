<?php

namespace App\Services\Catalogo;

use App\Models\Asset;
use App\Models\CatalogoSla;
use App\Models\MaintenanceOrder;
use App\Models\PriorityLevel;
use App\Models\SlaTimer;
use App\Services\Catalogo\Aptidao\AptidaoGate;

/**
 * G1 fase 7: SLA por grupo de equipamento x criticidade, em cima do SLA da F2.
 *
 * A criticidade do catalogo (critica|alta|media|baixa) e o nivel BASE da prioridade da OS (F1): prioridade personalizada do
 * tenant entra pelo `base_level`. So vale para tenant com a chave do catalogo ligada. Relogios que o grupo nao define
 * (ex.: Atendimento) continuam vindo da politica geral da F2 -- ver SlaService::definicao().
 */
class SlaDoGrupo
{
    /** @var array<string, string> prioridade => criticidade (por tenant+codigo) */
    private static array $niveis = [];

    public static function esquecer(): void
    {
        self::$niveis = [];
    }

    public static function criticidadeDaPrioridade(string $tenantId, string $codigo): string
    {
        return self::$niveis[$tenantId.'|'.$codigo] ??= (string) (PriorityLevel::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('code', $codigo)->value('base_level') ?: $codigo);
    }

    public static function para(MaintenanceOrder $os): ?CatalogoSla
    {
        if (blank($os->asset_id) || blank($os->priority) || blank($os->tenant_id) || ! AptidaoGate::ligada($os->tenant_id)) {
            return null;
        }

        $grupoId = Asset::withoutGlobalScopes()->where('tenant_id', $os->tenant_id)->where('id', $os->asset_id)->value('asset_category_id');

        if (! $grupoId) {
            return null;
        }

        return CatalogoSla::withoutGlobalScopes()
            ->where('tenant_id', $os->tenant_id)->where('grupo_id', $grupoId)->where('ativo', true)
            ->where('criticidade', self::criticidadeDaPrioridade((string) $os->tenant_id, (string) $os->priority))
            ->where(fn ($q) => $q->whereNotNull('resposta_min')->orWhereNotNull('resolucao_min'))
            ->orderBy('ordem')->first();
    }

    /** @return array<string, int> relogio => minutos (so os definidos) */
    public static function minutos(CatalogoSla $sla): array
    {
        return array_filter([
            SlaTimer::RESPONSE => $sla->resposta_min,
            SlaTimer::RESOLUTION => $sla->resolucao_min,
        ], fn ($m) => $m !== null && $m > 0);
    }
}
