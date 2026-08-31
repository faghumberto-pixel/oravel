<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropostaComercialReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makePropostaComItem(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Report '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Report '.uniqid(), 'slug' => 'tenant-report-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Report PDF-XYZ']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras']);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Item Report PDF', 'quantity' => 1, 'unit_price' => 100,
        ]);

        return [$admin, $proposta->fresh()];
    }

    public function test_download_gera_pdf_autenticado(): void
    {
        [$admin, $proposta] = $this->makePropostaComItem();
        $this->actingAs($admin);

        $response = $this->get(route('proposta-comercial.pdf', $proposta));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_print_individual_mostra_conteudo_da_proposta(): void
    {
        [$admin, $proposta] = $this->makePropostaComItem();
        $this->actingAs($admin);

        $response = $this->get(route('proposta-comercial.print', $proposta));

        $response->assertOk();
        $response->assertSee('Cliente Report PDF-XYZ');
        $response->assertSee('Item Report PDF');
    }
}
