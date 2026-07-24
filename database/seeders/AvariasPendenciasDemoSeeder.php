<?php

namespace Database\Seeders;

use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderPendencia;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Popula Avarias (EquipmentDamage) e Pendências (MaintenanceOrderPendencia)
 * nos 5 tenants de demonstração -- pedido explícito do usuário, mínimo de
 * 20 registros em cada tabela.
 *
 * Idempotência por META (não por existência simples, ver bug documentado
 * em FleetTrackingDemoSeeder::seedHorimeterHistory()): 3 dos 5 tenants já
 * tinham 1 avaria orgânica cada e todos já tinham 2 pendências -- um guard
 * "já existe, pula o tenant inteiro" deixaria eles sub-povoados. Em vez
 * disso, garante um PISO por tenant (cria só a diferença que falta), então
 * rodar de novo depois de já ter atingido o piso não duplica nada.
 */
class AvariasPendenciasDemoSeeder extends Seeder
{
    private const PISO_POR_TENANT = 5;

    private const SLUGS = [
        'torres-guindastes',
        'geradores-rmc',
        'construtora-alicerce-locacoes',
        'eventos-show-geradores',
        'hospital-vida-plena-energia',
    ];

    private const AVARIAS = [
        ['severity' => EquipmentDamage::SEVERITY_LEVE, 'damage_type' => EquipmentDamage::DAMAGE_TYPE_OUTRO, 'cause' => EquipmentDamage::CAUSE_DESGASTE_NATURAL, 'description' => 'Risco superficial na pintura, sem comprometer estrutura ou funcionamento.'],
        ['severity' => EquipmentDamage::SEVERITY_LEVE, 'damage_type' => EquipmentDamage::DAMAGE_TYPE_ELETRICO, 'cause' => EquipmentDamage::CAUSE_DESGASTE_NATURAL, 'description' => 'Lâmpada de sinalização queimada, substituição simples.'],
        ['severity' => EquipmentDamage::SEVERITY_MODERADA, 'damage_type' => EquipmentDamage::DAMAGE_TYPE_HIDRAULICO, 'cause' => EquipmentDamage::CAUSE_DESGASTE_NATURAL, 'description' => 'Pequeno vazamento de óleo hidráulico identificado na conexão da mangueira principal.'],
        ['severity' => EquipmentDamage::SEVERITY_MODERADA, 'damage_type' => EquipmentDamage::DAMAGE_TYPE_PNEU_ESTEIRA, 'cause' => EquipmentDamage::CAUSE_MAU_USO, 'description' => 'Desgaste irregular do pneu dianteiro, provável uso fora da pressão recomendada.'],
        ['severity' => EquipmentDamage::SEVERITY_MODERADA, 'damage_type' => EquipmentDamage::DAMAGE_TYPE_ESTRUTURAL, 'cause' => EquipmentDamage::CAUSE_DANO_CLIENTE, 'description' => 'Amassado na lateral da carcaça, compatível com colisão durante a operação no cliente.'],
        ['severity' => EquipmentDamage::SEVERITY_GRAVE, 'damage_type' => EquipmentDamage::DAMAGE_TYPE_MOTOR, 'cause' => EquipmentDamage::CAUSE_DESGASTE_NATURAL, 'description' => 'Ruído anormal no motor sob carga, indício de desgaste em rolamento interno.'],
        ['severity' => EquipmentDamage::SEVERITY_GRAVE, 'damage_type' => EquipmentDamage::DAMAGE_TYPE_ELETRICO, 'cause' => EquipmentDamage::CAUSE_MAU_USO, 'description' => 'Painel de comando com sinais de sobrecarga -- fiação queimada em parte do circuito.'],
    ];

    private const PENDENCIAS_ABERTAS = [
        'Falta peça de reposição em estoque para concluir o reparo.',
        'Aguardando aprovação do orçamento pelo cliente para prosseguir.',
        'Técnico especializado ainda não disponível para o diagnóstico completo.',
        'Peça encomendada ao fornecedor, sem previsão de entrega confirmada.',
        'Aguardando autorização do supervisor para desmontagem do componente.',
    ];

    private const PENDENCIAS_RESOLVIDAS = [
        'Peça recebida e substituída -- equipamento testado e liberado.',
        'Orçamento aprovado pelo cliente, serviço concluído no prazo.',
        'Técnico especializado realizou o diagnóstico e resolveu a falha.',
    ];

    public function run(): void
    {
        foreach (self::SLUGS as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            $user = User::where('tenant_id', $tenant->id)->first();

            if (! $user) {
                $this->command?->warn("Tenant '{$slug}' sem usuário -- pulando.");

                continue;
            }

            // As duas tabelas têm observers/policies que consultam auth()
            // (ex: EquipmentDamageObserver, notificações) -- Auth::login()
            // é o jeito correto num Seeder de console, actingAs() é só de teste.
            Auth::login($user);

            $orders = MaintenanceOrder::where('tenant_id', $tenant->id)->get();

            if ($orders->isEmpty()) {
                $this->command?->warn("Tenant '{$slug}' sem Ordens de Serviço -- pulando (avaria/pendência exige maintenance_order_id).");

                continue;
            }

            $this->seedAvarias($tenant->id, $user->id, $orders);
            $this->seedPendencias($tenant->id, $user->id, $orders);
        }
    }

    private function seedAvarias(string $tenantId, string $userId, Collection $orders): void
    {
        $existentes = EquipmentDamage::where('tenant_id', $tenantId)->count();
        $faltam = self::PISO_POR_TENANT - $existentes;

        if ($faltam <= 0) {
            return;
        }

        for ($i = 0; $i < $faltam; $i++) {
            $order = $orders->random();
            $template = self::AVARIAS[$i % count(self::AVARIAS)];

            EquipmentDamage::create([
                'tenant_id' => $tenantId,
                'maintenance_order_id' => $order->id,
                'asset_id' => $order->asset_id,
                'reported_by_user_id' => $userId,
                'severity' => $template['severity'],
                'damage_type' => $template['damage_type'],
                'cause' => $template['cause'],
                'description' => $template['description'],
                'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
            ]);
        }
    }

    private function seedPendencias(string $tenantId, string $userId, Collection $orders): void
    {
        $existentes = MaintenanceOrderPendencia::where('tenant_id', $tenantId)->count();
        $faltam = self::PISO_POR_TENANT - $existentes;

        if ($faltam <= 0) {
            return;
        }

        for ($i = 0; $i < $faltam; $i++) {
            $order = $orders->random();
            // Metade aberta, metade resolvida -- dá pra exercitar os dois
            // estados nos painéis que já consomem essa tabela (Histórico
            // do Patrimônio, Eventos e Falhas).
            $resolvida = $i % 2 === 0;

            MaintenanceOrderPendencia::create([
                'tenant_id' => $tenantId,
                'maintenance_order_id' => $order->id,
                'created_by_user_id' => $userId,
                'description' => $resolvida
                    ? self::PENDENCIAS_RESOLVIDAS[$i % count(self::PENDENCIAS_RESOLVIDAS)]
                    : self::PENDENCIAS_ABERTAS[$i % count(self::PENDENCIAS_ABERTAS)],
                'status' => $resolvida ? MaintenanceOrderPendencia::STATUS_RESOLVIDA : MaintenanceOrderPendencia::STATUS_ABERTA,
                'resolved_at' => $resolvida ? now()->subDays(random_int(1, 10)) : null,
                'resolved_by_user_id' => $resolvida ? $userId : null,
            ]);
        }
    }
}
