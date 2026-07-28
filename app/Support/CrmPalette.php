<?php

namespace App\Support;

use App\Models\Client;
use App\Models\SalesLead;

/**
 * Fonte única de cor por estágio (SalesLead::pipeline_stage) e por
 * segmento/nicho (Client::NICHE_*, reaproveitado por SalesLead.segment e
 * Tenant.segment) -- pedido do usuário 2026-07-28: "mais cor... por
 * estágio... fácil de atualização". Antes cada Blade (Kanban, Funil) tinha
 * seu próprio array duplicado; agora mudar uma cor aqui já reflete em
 * ambos, mais nas tabelas do Filament (SalesLeadResource, TenantResource)
 * via a chave 'filament'.
 *
 * 'filament' é o nome registrado em CentralPanelProvider::panel()->colors()
 * -- funciona direto em Tables\Columns\*::color('nome'). As demais chaves
 * são classes Tailwind literais pros Blade customizados (Kanban/Funil, que
 * não passam pelo componente Badge do Filament); precisam aparecer literais
 * em algum arquivo escaneado por tailwind.config.js (agora inclui
 * app/**\/*.php, ver comentário lá).
 */
class CrmPalette
{
    /**
     * @return array{filament: string, bg: string, border: string, text: string, soft: string, dot: string, ring: string}
     */
    public static function stage(?string $stage): array
    {
        return match ($stage) {
            SalesLead::STAGE_PROSPECCAO => [
                'filament' => 'crmSlate',
                'bg' => 'bg-slate-600',
                'border' => 'border-slate-600',
                'text' => 'text-slate-600 dark:text-slate-400',
                'soft' => 'bg-slate-50 dark:bg-slate-500/10',
                'dot' => 'bg-slate-500',
                // Classe literal (não montada em runtime) -- Tailwind JIT só
                // compila o que aparece como texto exato num arquivo
                // escaneado, então "ring-" . $cor . "-400" não funcionaria.
                'ring' => 'ring-slate-400',
            ],
            SalesLead::STAGE_CONTATO_QUALIFICADO => [
                'filament' => 'crmBlue',
                'bg' => 'bg-blue-600',
                'border' => 'border-blue-600',
                'text' => 'text-blue-600 dark:text-blue-400',
                'soft' => 'bg-blue-50 dark:bg-blue-500/10',
                'dot' => 'bg-blue-500',
                'ring' => 'ring-blue-400',
            ],
            SalesLead::STAGE_DEMONSTRACAO_REALIZADA => [
                'filament' => 'crmPurple',
                'bg' => 'bg-purple-600',
                'border' => 'border-purple-600',
                'text' => 'text-purple-600 dark:text-purple-400',
                'soft' => 'bg-purple-50 dark:bg-purple-500/10',
                'dot' => 'bg-purple-500',
                'ring' => 'ring-purple-400',
            ],
            SalesLead::STAGE_PROPOSTA_ENVIADA => [
                'filament' => 'crmOrange',
                'bg' => 'bg-orange-500',
                'border' => 'border-orange-500',
                'text' => 'text-orange-600 dark:text-orange-400',
                'soft' => 'bg-orange-50 dark:bg-orange-500/10',
                'dot' => 'bg-orange-500',
                'ring' => 'ring-orange-400',
            ],
            SalesLead::STAGE_GANHO => [
                'filament' => 'crmEmerald',
                'bg' => 'bg-emerald-600',
                'border' => 'border-emerald-600',
                'text' => 'text-emerald-600 dark:text-emerald-400',
                'soft' => 'bg-emerald-50 dark:bg-emerald-500/10',
                'dot' => 'bg-emerald-500',
                'ring' => 'ring-emerald-400',
            ],
            SalesLead::STAGE_PERDIDO => [
                'filament' => 'danger',
                'bg' => 'bg-red-600',
                'border' => 'border-red-600',
                'text' => 'text-red-600 dark:text-red-400',
                'soft' => 'bg-red-50 dark:bg-red-500/10',
                'dot' => 'bg-red-500',
                'ring' => 'ring-red-400',
            ],
            default => [
                'filament' => 'gray',
                'bg' => 'bg-gray-600',
                'border' => 'border-gray-600',
                'text' => 'text-gray-600 dark:text-gray-400',
                'soft' => 'bg-gray-50 dark:bg-gray-500/10',
                'dot' => 'bg-gray-500',
                'ring' => 'ring-gray-400',
            ],
        };
    }

    /**
     * @return array{filament: string, bg: string, border: string, text: string, soft: string, dot: string, hex: string}
     */
    public static function segment(?string $segment): array
    {
        return match ($segment) {
            Client::NICHE_EVENTOS => [
                'filament' => 'crmPink',
                'bg' => 'bg-pink-600',
                'border' => 'border-pink-600',
                'text' => 'text-pink-600 dark:text-pink-400',
                'soft' => 'bg-pink-50 dark:bg-pink-500/10',
                'dot' => 'bg-pink-500',
                // Mesmo tom (pink-600), em hex -- pra widgets Chart.js
                // (LeadsBySegmentChart) que nao leem classe Tailwind.
                'hex' => '#db2777',
            ],
            Client::NICHE_INDUSTRIAL_HOSPITALAR => [
                'filament' => 'crmCyan',
                'bg' => 'bg-cyan-600',
                'border' => 'border-cyan-600',
                'text' => 'text-cyan-600 dark:text-cyan-400',
                'soft' => 'bg-cyan-50 dark:bg-cyan-500/10',
                'dot' => 'bg-cyan-500',
                'hex' => '#0891b2',
            ],
            Client::NICHE_CONSTRUCAO_CIVIL => [
                'filament' => 'crmAmber',
                'bg' => 'bg-amber-600',
                'border' => 'border-amber-600',
                'text' => 'text-amber-600 dark:text-amber-400',
                'soft' => 'bg-amber-50 dark:bg-amber-500/10',
                'dot' => 'bg-amber-500',
                'hex' => '#d97706',
            ],
            default => [
                'filament' => 'gray',
                'bg' => 'bg-gray-500',
                'border' => 'border-gray-500',
                'text' => 'text-gray-500 dark:text-gray-400',
                'soft' => 'bg-gray-50 dark:bg-gray-500/10',
                'dot' => 'bg-gray-400',
                'hex' => '#6b7280',
            ],
        };
    }

    /**
     * Mapa estágio => cor, na ordem de stageLabels() (usado pelo Kanban/
     * Funil pra montar os arrays de cor de cada coluna/faixa de uma vez).
     *
     * @return array<string, array{filament: string, bg: string, border: string, text: string, soft: string, dot: string}>
     */
    public static function stageMap(): array
    {
        $map = [];
        foreach (array_keys(SalesLead::stageLabels()) as $stage) {
            $map[$stage] = self::stage($stage);
        }

        return $map;
    }
}
