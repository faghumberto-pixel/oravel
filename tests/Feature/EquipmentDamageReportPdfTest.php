<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-25: investigação encontrou que o laudo jurídico
 * de avaria (a peça que seria usada numa disputa/cobrança) não exibia a
 * causa atribuída (mau_uso/dano_cliente/desgaste_natural) nem o valor do
 * orçamento indenizatório aprovado -- o documento provava "o dano
 * aconteceu" mas não "de quem foi a culpa e quanto foi cobrado", que é
 * justamente o ponto central da pergunta do diretor de locadora.
 */
class EquipmentDamageReportPdfTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Laudo '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_equipment_damages'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Laudo '.uniqid(), 'slug' => 'tenant-laudo-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_laudo_pdf_exibe_causa_e_orcamento_indenizatorio_aprovado(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Teste', 'status' => Asset::STATUS_DISPONIVEL,
        ]);
        $os = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'os_number' => 'OS-'.uniqid(), 'status' => 'concluida', 'maintenance_type' => 'corretiva',
        ]);

        $damage = EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_order_id' => $os->id,
            'reported_by_user_id' => $admin->id,
            'severity' => 'grave', 'damage_type' => 'estrutural', 'cause' => EquipmentDamage::CAUSE_MAU_USO,
            'description' => 'Dano por mau uso do cliente.',
        ]);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);

        Quote::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'quotable_type' => EquipmentDamage::class, 'quotable_id' => $damage->id,
            'type' => Quote::TYPE_INTERNO, 'status' => Quote::STATUS_APROVADO, 'total_value' => 2500,
        ]);

        $response = $this->actingAs($admin)->get(route('equipment-damages.laudo.pdf', $damage));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        // Renderiza a mesma view isoladamente pra confirmar que a causa e o
        // valor do orçamento aprovado aparecem no HTML fonte do laudo --
        // assertOk() sozinho não prova que o conteúdo específico está lá.
        $html = view('pdf.equipment_damage_report', [
            'damage' => $damage->fresh()->load('quotes'),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->render();

        $this->assertStringContainsString('Mau Uso', $html);
        $this->assertStringContainsString('Cobrável ao Cliente?', $html);
        $this->assertStringContainsString('R$ 2.500,00', $html);
    }

    public function test_laudo_pdf_funciona_sem_causa_nem_orcamento(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Teste', 'status' => Asset::STATUS_DISPONIVEL,
        ]);
        $os = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'os_number' => 'OS-'.uniqid(), 'status' => 'concluida', 'maintenance_type' => 'corretiva',
        ]);

        $damage = EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_order_id' => $os->id,
            'reported_by_user_id' => $admin->id,
            'severity' => 'leve', 'damage_type' => 'estetico', 'cause' => null,
            'description' => 'Ainda não classificado.',
        ]);

        $response = $this->actingAs($admin)->get(route('equipment-damages.laudo.pdf', $damage));

        $response->assertOk();
    }
}
