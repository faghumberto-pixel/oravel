<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EpiDelivery;
use App\Models\EpiSpecification;
use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MaterialStockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Popula o modulo de Gestao de EPI (compliance NR-6) pro tenant Demo
 * Empilhadeiras. Cobre os estados reais que o dashboard/relatorio mostram:
 * ativo em dia, bloqueado por CA vencido (na entrega e retroativo via
 * touch(), replicando o que epi:check-ca-expirations faz), vida util
 * estourada, emprestimo com devolucao atrasada, devolvido e substituido
 * (cadeia via replaced_by_delivery_id). Roda o fluxo real (MaterialStockService)
 * igual o Resource faz, nao seta quantity/estoque na mao.
 */
class DemoEpiSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(fn () => $this->seed());
    }

    private function seed(): void
    {
        $tenant = Tenant::where('name', 'Demo Empilhadeiras')->firstOrFail();
        $tenantId = $tenant->id;
        $admin = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->firstOrFail();
        $unit = InternalUnit::withoutGlobalScopes()->where('tenant_id', $tenantId)->firstOrFail();
        $stockService = app(MaterialStockService::class);

        $employees = collect([
            ['name' => 'Carlos Eduardo Silva', 'cpf' => '11122233344', 'role_title' => 'Técnico de Manutenção'],
            ['name' => 'Fernanda Oliveira Santos', 'cpf' => '22233344455', 'role_title' => 'Técnica de Campo'],
            ['name' => 'Roberto Alves Pereira', 'cpf' => '33344455566', 'role_title' => 'Operador de Pátio'],
            ['name' => 'Juliana Costa Lima', 'cpf' => '44455566677', 'role_title' => 'Técnica de Manutenção'],
        ])->map(fn (array $data) => Employee::create($data + [
            'tenant_id' => $tenantId,
            'status' => Employee::STATUS_ATIVO,
            'admission_date' => now()->subYears(2),
        ]));

        [$carlos, $fernanda, $roberto, $juliana] = $employees->all();

        $epis = collect([
            ['name' => 'Capacete de Segurança Classe A', 'sku' => 'EPI-CAPACETE-01', 'type' => EpiSpecification::TYPE_CAPACETE, 'ca' => 'CA-38921', 'lifespan' => 1825, 'cost' => 45],
            ['name' => 'Luva de Raspa Reforçada', 'sku' => 'EPI-LUVA-01', 'type' => EpiSpecification::TYPE_LUVA, 'size' => 'G', 'ca' => 'CA-41207', 'lifespan' => 90, 'cost' => 22],
            ['name' => 'Bota de Segurança com Bico Composite', 'sku' => 'EPI-BOTA-01', 'type' => EpiSpecification::TYPE_BOTA, 'size' => '42', 'ca' => 'CA-29845', 'lifespan' => 365, 'cost' => 189],
            ['name' => 'Óculos de Proteção Ampla Visão', 'sku' => 'EPI-OCULOS-01', 'type' => EpiSpecification::TYPE_OCULOS, 'ca' => 'CA-35410', 'lifespan' => 180, 'cost' => 18],
            ['name' => 'Protetor Auricular Tipo Concha', 'sku' => 'EPI-AURICULAR-01', 'type' => EpiSpecification::TYPE_PROTETOR_AURICULAR, 'ca' => 'CA-19387', 'lifespan' => 730, 'cost' => 65],
            ['name' => 'Cinto de Segurança Paraquedista', 'sku' => 'EPI-CINTO-01', 'type' => EpiSpecification::TYPE_CINTO_SEGURANCA, 'ca' => 'CA-22156', 'lifespan' => 730, 'cost' => 340, 'emprestimo' => true],
        ])->map(function (array $data) use ($tenantId) {
            $material = Material::create([
                'tenant_id' => $tenantId, 'name' => $data['name'], 'sku' => $data['sku'],
                'current_stock' => 30, 'unit_cost' => $data['cost'],
            ]);

            EpiSpecification::create([
                'tenant_id' => $tenantId, 'material_id' => $material->id,
                'epi_type' => $data['type'], 'size_label' => $data['size'] ?? null,
                'ca_number' => $data['ca'], 'ca_manufacturer' => 'Proteção Total EPI Ltda',
                'ca_validade' => now()->addYear(),
                'estimated_lifespan_days' => $data['lifespan'],
                'default_ownership_mode' => ($data['emprestimo'] ?? false)
                    ? EpiSpecification::OWNERSHIP_EMPRESTIMO_TEMPORARIO
                    : EpiSpecification::OWNERSHIP_DEFINITIVA,
            ]);

            return $material;
        });

        [$capacete, $luva, $bota, $oculos, $auricular, $cinto] = $epis->all();

        $entregar = function (Employee $employee, Material $material, array $overrides = []) use ($tenantId, $unit, $admin, $stockService) {
            $delivery = EpiDelivery::create(array_merge([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'material_id' => $material->id,
                'internal_unit_id' => $unit->id,
                'quantity' => 1,
                'reason' => EpiDelivery::REASON_ENTREGA_INICIAL,
                'ownership_mode' => $material->epiSpecification->default_ownership_mode,
                'delivered_at' => now()->subDays(10),
                'delivered_by_user_id' => $admin->id,
            ], $overrides));

            $stockService->consume($material, $unit, $delivery->quantity, $delivery, $admin->id);
            $movement = \App\Models\MaterialStockMovement::where('reference_type', EpiDelivery::class)
                ->where('reference_id', $delivery->id)->latest()->first();
            if ($movement) {
                $delivery->updateQuietly(['material_stock_movement_id' => $movement->id]);
            }

            return $delivery;
        };

        // Carlos: capacete + luva + bota, tudo normal (CA em dia)
        $entregar($carlos, $capacete);
        $entregar($carlos, $luva, ['delivered_at' => now()->subDays(5)]);
        $entregar($carlos, $bota, ['delivered_at' => now()->subDays(200)]);

        // Fernanda: óculos com vida útil estourada (entregue ha mais dias que os 180 de lifespan)
        $entregar($fernanda, $oculos, ['delivered_at' => now()->subDays(210)]);
        $entregar($fernanda, $capacete, ['delivered_at' => now()->subDays(30)]);

        // Roberto: protetor auricular com CA JA vencido no momento da entrega -- trigger bloqueia na hora
        $auricular->epiSpecification->update(['ca_validade' => now()->subDays(15)]);
        $entregar($roberto, $auricular, ['delivered_at' => now()->subDays(2)]);

        // Juliana: cinto de segurança em empréstimo temporário, devolução vencida há 5 dias
        $entregar($juliana, $cinto, [
            'delivered_at' => now()->subDays(20),
            'expected_return_at' => now()->subDays(5),
        ]);

        // Ciclo fechado: Carlos devolveu uma luva antiga (boa condição, voltou pro estoque)
        $luvaDevolvida = $entregar($carlos, $luva, ['reason' => EpiDelivery::REASON_TROCA_DESGASTE, 'delivered_at' => now()->subDays(95)]);
        $luvaDevolvida->update([
            'status' => EpiDelivery::STATUS_DEVOLVIDO,
            'returned_at' => now()->subDays(90),
            'returned_by_user_id' => $admin->id,
            'returned_condition' => EpiDelivery::RETURNED_CONDITION_BOA,
        ]);
        $stockService->receive($luva, $unit, 1, $luvaDevolvida, $admin->id);

        // Cadeia de substituição: bota antiga trocada por desgaste, nova entrega vinculada
        $botaAntiga = $entregar($roberto, $bota, ['delivered_at' => now()->subDays(400)]);
        $botaNova = $entregar($roberto, $bota, ['reason' => EpiDelivery::REASON_TROCA_DESGASTE, 'delivered_at' => now()->subDays(3)]);
        $botaAntiga->update(['status' => EpiDelivery::STATUS_SUBSTITUIDO, 'replaced_by_delivery_id' => $botaNova->id]);

        $this->command?->info('Demo EPI: '.$employees->count().' colaboradores, '.$epis->count().' EPIs cadastrados, '
            .EpiDelivery::where('tenant_id', $tenantId)->count().' entregas.');
    }
}
