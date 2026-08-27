<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\MaintenanceDueAlert;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MaintenanceDueNotification;
use Illuminate\Console\Command;

/**
 * Roda diariamente (App\Console\Kernel::schedule()) e avisa a role
 * "admin" de cada tenant quando uma preventiva vence -- ate' aqui, o
 * calculo (MaintenancePlan::dueStatusForAsset(), ja existia) so' era
 * mostrado ao vivo quando alguem abria o Painel de Criticidade ou a
 * tela de uma O.S., nunca de forma proativa. Mesma logica de
 * agrupamento por-Ativo/por-Grupo de App\Filament\Pages\PainelCriticidade.
 *
 * Dedupe via MaintenanceDueAlert: nao repete o alerta do mesmo par
 * Ativo+Plano por 7 dias; quando a preventiva deixa de estar vencida
 * (foi executada), a linha e apagada pra permitir um alerta novo no
 * proximo ciclo.
 */
class CheckMaintenanceDueAlerts extends Command
{
    protected $signature = 'maintenance:check-due-alerts';

    protected $description = 'Verifica preventivas vencidas por tenant e notifica os admins (dedupe de 7 dias)';

    public function handle(): int
    {
        $totalAlertas = 0;

        foreach (Tenant::all() as $tenant) {
            $assets = Asset::where('tenant_id', $tenant->id)
                ->whereNotIn('status', ['aguardando_triagem'])
                ->get();

            if ($assets->isEmpty()) {
                continue;
            }

            $planos = MaintenancePlan::where('tenant_id', $tenant->id)->get();
            $planosPorAsset = $planos->whereNotNull('asset_id')->groupBy('asset_id');
            $planosPorGrupo = $planos->whereNotNull('checklist_group_id')->groupBy('checklist_group_id');

            foreach ($assets as $asset) {
                $planosDoAtivo = $planosPorAsset->get($asset->id, collect())
                    ->merge($asset->checklist_group_id ? $planosPorGrupo->get($asset->checklist_group_id, collect()) : collect());

                $statusPorPlano = [];
                foreach ($planosDoAtivo as $plano) {
                    $status = $plano->dueStatusForAsset($asset);
                    $statusPorPlano[] = ['plano' => $plano, 'status' => $status];
                    $totalAlertas += $this->checkPlano($tenant, $asset, $plano, $status);
                }

                $this->syncCriticalBlock($asset, $statusPorPlano);
            }
        }

        $this->info("Verificação concluída. {$totalAlertas} alerta(s) novo(s) enviado(s).");

        return Command::SUCCESS;
    }

    private function checkPlano(Tenant $tenant, Asset $asset, MaintenancePlan $plano, array $status): int
    {
        $alertaExistente = MaintenanceDueAlert::where('asset_id', $asset->id)
            ->where('maintenance_plan_id', $plano->id)
            ->first();

        if (! $status['is_overdue']) {
            $alertaExistente?->delete();

            return 0;
        }

        if ($alertaExistente && $alertaExistente->alerted_at->diffInDays(now()) < 7) {
            return 0;
        }

        $this->notifyAdmins($tenant, $asset, $plano, $status);

        // Opt-in por plano (auto_create_order, default false) -- nao muda
        // comportamento de planos ja cadastrados. Soma a notificacao, nao
        // substitui: mesmo com a O.S. criada sozinha, o admin ainda e'
        // avisado. Dedupe de 7 dias (MaintenanceDueAlert) ja impede criar
        // mais de uma O.S. pro mesmo Ativo+Plano em sequencia.
        if ($plano->auto_create_order) {
            $this->createOrderAutomatically($tenant, $asset, $plano, $status);
        }

        MaintenanceDueAlert::updateOrCreate(
            ['asset_id' => $asset->id, 'maintenance_plan_id' => $plano->id],
            ['tenant_id' => $tenant->id, 'alerted_at' => now()]
        );

        return 1;
    }

    /**
     * Bloqueio automático (pedido do usuário 2026-08-27): qualquer item
     * CRÍTICO vencido move o Asset pra STATUS_MANUTENCAO, impedindo
     * locação nova (ver ContractResource). Reverte pro status real de
     * antes (não sempre "disponível" -- pode ter sido "locado") quando
     * nenhum item crítico do Ativo estiver mais vencido. Não mexe em nada
     * se o Ativo já estava em STATUS_MANUTENCAO por outro motivo manual
     * (blocked_by_pmp_at nulo nesse caso, então a reversão não se aplica).
     */
    private function syncCriticalBlock(Asset $asset, array $statusPorPlano): void
    {
        $temItemCriticoVencido = collect($statusPorPlano)
            ->contains(fn (array $item) => $item['plano']->is_critical && $item['status']['is_overdue']);

        if ($temItemCriticoVencido) {
            if ($asset->blocked_by_pmp_at) {
                return;
            }

            $asset->forceFill([
                'status_before_pmp_block' => $asset->status,
                'blocked_by_pmp_at' => now(),
                'status' => Asset::STATUS_MANUTENCAO,
            ])->save();

            return;
        }

        if (! $asset->blocked_by_pmp_at) {
            return;
        }

        $asset->forceFill([
            'status' => $asset->status_before_pmp_block ?? Asset::STATUS_DISPONIVEL,
            'status_before_pmp_block' => null,
            'blocked_by_pmp_at' => null,
        ])->save();
    }

    /**
     * Pedido do usuário 2026-08-27: precisa estar claro que a OS é PMP do
     * grupo, não uma corretiva qualquer -- prefixo "PMP · {grupo}" na
     * descrição (só quando o plano é template de grupo, isGroupTemplate())
     * + campo estruturado origin='pmp_auto' pra badge/filtro na tabela
     * (MaintenanceOrderResource), sem depender de parsear texto.
     */
    private function createOrderAutomatically(Tenant $tenant, Asset $asset, MaintenancePlan $plano, array $status): void
    {
        $prefix = $plano->isGroupTemplate() ? 'PMP · '.$plano->checklistGroup?->name.' — ' : '';

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'client_id' => $asset->client_id,
            'maintenance_plan_id' => $plano->id,
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Aberto',
            'origin' => 'pmp_auto',
            'description' => sprintf(
                '%s"%s" vencida há %s horas (previsto para %sh, horímetro atual %sh).',
                $prefix,
                $plano->name,
                number_format($status['overdue_hours'], 1),
                number_format($status['due_at_hours'], 1),
                number_format((float) $asset->horimetro_atual, 1),
            ),
        ]);
    }

    /**
     * Ver EquipmentReplacementObserver::notifyRole() -- mesmo motivo: nao
     * usar User::role($roleName) direto, pois Spatie resolve por nome
     * globalmente (ignora tenant_id) e falha silenciosamente pra qualquer
     * tenant que nao seja o primeiro a ter um papel com aquele nome.
     */
    private function notifyAdmins(Tenant $tenant, Asset $asset, MaintenancePlan $plano, array $status): void
    {
        $role = Role::where('name', 'admin')
            ->where('guard_name', 'web')
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)->where('tenant_id', $tenant->id)->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new MaintenanceDueNotification($asset, $status));
        }
    }
}
