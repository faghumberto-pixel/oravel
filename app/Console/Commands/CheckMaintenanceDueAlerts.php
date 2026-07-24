<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\MaintenanceDueAlert;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
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

                foreach ($planosDoAtivo as $plano) {
                    $totalAlertas += $this->checkPlano($tenant, $asset, $plano);
                }
            }
        }

        $this->info("Verificação concluída. {$totalAlertas} alerta(s) novo(s) enviado(s).");

        return Command::SUCCESS;
    }

    private function checkPlano(Tenant $tenant, Asset $asset, MaintenancePlan $plano): int
    {
        $status = $plano->dueStatusForAsset($asset);

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

    private function createOrderAutomatically(Tenant $tenant, Asset $asset, MaintenancePlan $plano, array $status): void
    {
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'client_id' => $asset->client_id,
            'maintenance_plan_id' => $plano->id,
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Aberto',
            'description' => sprintf(
                'Gerada automaticamente: "%s" vencida há %s horas (previsto para %sh, horímetro atual %sh).',
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
            Notification::make()
                ->title('Preventiva vencida: '.$asset->name)
                ->body(sprintf(
                    'Patrimônio %s — %s horas de atraso (previsto para %sh, horímetro atual %sh).',
                    $asset->patrimonio,
                    number_format($status['overdue_hours'], 1),
                    number_format($status['due_at_hours'], 1),
                    number_format((float) $asset->horimetro_atual, 1),
                ))
                ->warning()
                ->actions([
                    Action::make('view')
                        ->button()
                        ->url(route('filament.admin.resources.assets.edit', $asset->id)),
                ])
                ->sendToDatabase($recipient);
        }
    }
}
