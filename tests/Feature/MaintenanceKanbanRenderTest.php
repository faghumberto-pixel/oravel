<?php

namespace Tests\Feature;

use App\Filament\Pages\MaintenanceKanban;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceKanbanRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_kanban_page_renders_with_cards_in_every_column(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Kanban '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Kanban '.uniqid(), 'slug' => 'tenant-kanban-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Kanban', 'patrimonio' => 'PAT-01', 'status' => 'disponivel']);

        foreach (['aguardando_diagnostico', 'em_manutencao', 'aguardando_peca', 'teste_qualidade', 'pendencia', 'concluido'] as $status) {
            MaintenanceOrder::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
                'description' => 'OS '.$status, 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
                'internal_status' => $status,
            ]);
        }

        $this->actingAs($admin);

        $response = $this->get(MaintenanceKanban::getUrl());

        $response->assertOk();
        $response->assertSee('Aguardando Diagnóstico');
        $response->assertSee('Em Manutenção');
        $response->assertSee('Teste de Qualidade');
        $response->assertSee('Concluído');
        $response->assertSee('Gerador Kanban');
    }
}
