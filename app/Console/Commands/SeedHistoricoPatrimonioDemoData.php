<?php

namespace App\Console\Commands;

use App\Models\AbcMatrix;
use App\Models\Asset;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderPendencia;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pedido do usuário 29/09/2026: a página "Histórico do Patrimônio"
 * (App\Filament\Pages\HistoricoPatrimonio) é uma timeline DERIVADA em tempo
 * real de 5 tabelas (AbcMatrixHistory, EquipmentDamage, MaintenanceOrder,
 * MaintenanceOrderPendencia, EquipmentReplacement) -- não tem tabela própria
 * pra "seedar" diretamente. `demo:seed-ativo-frota` só tocou 1 dessas 5
 * fontes (MaintenanceOrder, 3 registros, e só pra 3 dos 5 ativos), então a
 * timeline ficava vazia/rala pra maioria dos ativos -- daí a reclamação de
 * "não populou automático". Este comando completa as outras fontes pros
 * mesmos 5 ativos do seed de frota, pra timeline nascer rica sem precisar
 * de tabela própria.
 *
 * Idempotente: aborta se já existir qualquer AbcMatrix pro tenant (a não
 * ser --force).
 */
class SeedHistoricoPatrimonioDemoData extends Command
{
    protected $signature = 'demo:seed-historico-patrimonio {email=faghumberto@gmail.com} {--force : Roda mesmo se já existir dado do seed anterior}';

    protected $description = 'Completa Matriz ABC, Avaria e Pendência de OS pros Ativos criados por demo:seed-ativo-frota, pra a timeline do Histórico do Patrimônio nascer populada';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Usuário não encontrado.');

            return self::FAILURE;
        }

        $tenantId = $user->tenant_id;

        if (! $this->option('force') && AbcMatrix::where('tenant_id', $tenantId)->exists()) {
            $this->warn('Dataset já parece existir (Matriz ABC encontrada). Use --force pra rodar de novo mesmo assim.');

            return self::SUCCESS;
        }

        Auth::login($user);

        DB::transaction(function () use ($tenantId, $user) {
            $escavadeira = Asset::where('tenant_id', $tenantId)->where('name', 'Escavadeira Hidráulica CAT 320')->firstOrFail();
            $retroescavadeira = Asset::where('tenant_id', $tenantId)->where('name', 'Retroescavadeira 4x4')->firstOrFail();
            $guindaste = Asset::where('tenant_id', $tenantId)->where('name', 'Guindaste Munck 15t')->firstOrFail();
            $guincho = Asset::where('tenant_id', $tenantId)->where('name', 'Guincho de Reboque Pesado')->firstOrFail();
            $caminhao = Asset::where('tenant_id', $tenantId)->where('name', 'Caminhão Truck 6x2 Carroceria')->firstOrFail();

            // Matriz ABC pros 5 -- cada create() dispara AbcMatrixObserver,
            // que grava em AbcMatrixHistory (tag "criticidade" na timeline
            // e badge no Dossiê do Ativo).
            $this->classify($tenantId, $escavadeira, 'A', 'Alto valor de aquisição, uso intensivo em obra ativa.');
            $this->classify($tenantId, $retroescavadeira, 'B', 'Equipamento de apoio, uso moderado.');
            $this->classify($tenantId, $guindaste, 'A', 'Içamento crítico, exposição a risco de acidente e multa contratual em caso de parada.');
            $this->classify($tenantId, $guincho, 'B', 'Atendimento pontual/emergencial, uso variável.');
            $this->classify($tenantId, $caminhao, 'B', 'Uso regular em rota fixa, criticidade moderada.');

            // Retroescavadeira e Guincho não tinham nenhuma O.S. -- corretiva
            // concluída pra cada um, igual ao padrão de seedWorkHistory() do
            // seed de frota.
            $this->correctiveOrder($tenantId, $retroescavadeira, 'Troca de mangueira hidráulica com vazamento identificado em inspeção de rotina.');
            $this->correctiveOrder($tenantId, $guincho, 'Substituição do cabo de aço do guincho por desgaste.');

            // Pendência aberta na O.S. preventiva já existente do Guindaste
            // (peça aguardando reposição -- fica em aberto de propósito, pra
            // aparecer no painel de Eventos & Falhas também).
            $osGuindaste = MaintenanceOrder::where('tenant_id', $tenantId)
                ->where('asset_id', $guindaste->id)
                ->where('maintenance_type', MaintenanceOrder::TYPE_PREVENTIVE)
                ->firstOrFail();

            MaintenanceOrderPendencia::create([
                'tenant_id' => $tenantId,
                'maintenance_order_id' => $osGuindaste->id,
                'description' => 'Cabo de aço com sinais de desgaste além do previsto — aguardando peça de reposição do fornecedor.',
                'created_by_user_id' => $user->id,
                'status' => MaintenanceOrderPendencia::STATUS_ABERTA,
            ]);

            // Avaria leve no Caminhão, já resolvida -- presa a uma O.S.
            // corretiva própria (maintenance_order_id é obrigatório na
            // tabela). Cobre o fluxo de avarias+dossiê no ativo de
            // transporte, que ainda não tinha nenhuma.
            $osCaminhao = $this->correctiveOrder($tenantId, $caminhao, 'Reparo de amassado na lateral da carroceria, identificado no retorno da rota.');

            EquipmentDamage::create([
                'tenant_id' => $tenantId,
                'maintenance_order_id' => $osCaminhao->id,
                'asset_id' => $caminhao->id,
                'reported_by_user_id' => $user->id,
                'severity' => EquipmentDamage::SEVERITY_LEVE,
                'damage_type' => EquipmentDamage::DAMAGE_TYPE_ESTRUTURAL,
                'cause' => EquipmentDamage::CAUSE_DANO_CLIENTE,
                'description' => 'Amassado na lateral da carroceria durante descarga no cliente.',
                'status' => EquipmentDamage::STATUS_RESOLVIDO,
                'estimated_cost' => 850.00,
            ]);
        });

        Auth::logout();

        $this->info('Histórico do Patrimônio de demonstração completado.');

        return self::SUCCESS;
    }

    private function classify(string $tenantId, Asset $asset, string $nivel, string $descricao): AbcMatrix
    {
        return AbcMatrix::create([
            'tenant_id' => $tenantId,
            'asset_id' => $asset->id,
            'nivel' => $nivel,
            'descricao' => $descricao,
        ]);
    }

    private function correctiveOrder(string $tenantId, Asset $asset, string $description): MaintenanceOrder
    {
        return MaintenanceOrder::create([
            'tenant_id' => $tenantId,
            'asset_id' => $asset->id,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'description' => $description,
            'status' => 'Concluída',
            'started_at' => now()->subDays(6),
            'finished_at' => now()->subDays(5),
            'labor_cost' => 280.00,
            'material_cost' => 320.00,
            'total_order_cost' => 600.00,
        ]);
    }
}
