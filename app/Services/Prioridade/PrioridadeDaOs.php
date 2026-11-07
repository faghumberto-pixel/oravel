<?php

namespace App\Services\Prioridade;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\PriorityLevel;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Prioridade da O.S. (F1 da integracao criticidade -> prioridade -> SLA).
 *
 * Padrao automatico: criticidade do ATIVO (Matriz ABC) -> `default_priority` do nivel (fallback: urgente = alta, resto = media);
 * Emergencia sobe UM nivel no catalogo (limitado ao topo). Alteracao manual exige justificativa e fica no log de atividades.
 */
class PrioridadeDaOs
{
    public const AUTO = 'auto';

    public const MANUAL = 'manual';

    private const PADRAO = [
        ['code' => 'critica', 'name' => 'Crítica', 'color' => '#dc2626', 'sort_order' => 1],
        ['code' => 'alta', 'name' => 'Alta', 'color' => '#f97316', 'sort_order' => 2],
        ['code' => 'media', 'name' => 'Média', 'color' => '#eab308', 'sort_order' => 3],
        ['code' => 'baixa', 'name' => 'Baixa', 'color' => '#22c55e', 'sort_order' => 4],
    ];

    /** @var array<string, bool> */
    private static array $garantidos = [];

    /** Tenant sem nenhum nivel (criado depois da migration) ganha os 4 do sistema. */
    public static function garantirPadrao(string $tenantId): void
    {
        if (isset(self::$garantidos[$tenantId])) {
            return;
        }

        if (! PriorityLevel::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            PriorityLevel::withoutGlobalScopes()->insert(array_map(fn (array $n) => $n + [
                'id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'base_level' => $n['code'],
                'is_system' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ], self::PADRAO));
        }

        self::$garantidos[$tenantId] = true;
    }

    /** Niveis ativos do tenant, do mais alto para o mais baixo. */
    public static function niveis(string $tenantId): Collection
    {
        self::garantirPadrao($tenantId);

        return PriorityLevel::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @return array<string, string> codigo => nome */
    public static function opcoes(string $tenantId): array
    {
        return self::niveis($tenantId)->pluck('name', 'code')->all();
    }

    public static function sugerir(string $tenantId, ?string $assetId, ?string $tipo): string
    {
        $niveis = self::niveis($tenantId)->values();
        $codigos = $niveis->pluck('code')->all();

        $ativo = $assetId ? Asset::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($assetId) : null;
        $criticidade = $ativo?->currentCriticalityLevel();

        $codigo = $criticidade?->default_priority ?: ($criticidade?->is_urgent ? 'alta' : 'media');

        if (! in_array($codigo, $codigos, true)) {
            $codigo = $niveis->firstWhere('base_level', 'media')?->code ?? ($codigos[0] ?? 'media');
        }

        if ($tipo === MaintenanceOrder::TYPE_EMERGENCIA) {
            $pos = array_search($codigo, $codigos, true);
            if ($pos !== false && $pos > 0) {
                $codigo = $codigos[$pos - 1];
            }
        }

        return $codigo;
    }

    /**
     * @throws ValidationException
     */
    public static function alterar(MaintenanceOrder $os, string $codigo, string $justificativa, ?User $usuario = null): void
    {
        $justificativa = trim($justificativa);

        if (mb_strlen($justificativa) < 5) {
            throw ValidationException::withMessages(['justificativa' => 'Informe a justificativa da mudança (mínimo 5 caracteres).']);
        }

        if (! self::niveis((string) $os->tenant_id)->contains('code', $codigo)) {
            throw ValidationException::withMessages(['priority' => 'Prioridade inexistente ou inativa.']);
        }

        $anterior = $os->priority;

        if ($anterior === $codigo) {
            return;
        }

        $os->update(['priority' => $codigo, 'priority_source' => self::MANUAL]);

        activity('prioridade_os')
            ->performedOn($os)
            ->causedBy($usuario ?? auth()->user())
            ->withProperties(['de' => $anterior, 'para' => $codigo, 'justificativa' => $justificativa])
            ->event('prioridade_alterada')
            ->log("Prioridade alterada de {$anterior} para {$codigo}");
    }
}
