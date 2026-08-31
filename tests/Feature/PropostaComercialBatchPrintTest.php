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

class PropostaComercialBatchPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_print_lista_propostas_filtradas_por_status(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Batch '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Batch '.uniqid(), 'slug' => 'tenant-batch-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Batch']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras']);

        $rascunho = PropostaComercial::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id]);
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $rascunho->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Item Rascunho Batch', 'quantity' => 1, 'unit_price' => 100,
        ]);

        $enviada = PropostaComercial::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id]);
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $enviada->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Item Enviada Batch', 'quantity' => 1, 'unit_price' => 100,
        ]);
        $enviada->refresh();
        $enviada->enviarParaComercial();

        $this->actingAs($admin);

        $response = $this->get(route('proposta-comercial.print-batch', ['status' => PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL]));

        $response->assertOk();
        $response->assertSee('Item Enviada Batch');
        $response->assertDontSee('Item Rascunho Batch');
    }

    public function test_batch_print_por_ids_especificos(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Batch Ids '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Batch Ids '.uniqid(), 'slug' => 'tenant-batch-ids-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Batch Ids']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras']);

        $proposta = PropostaComercial::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id]);
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Item Selecionado Batch', 'quantity' => 1, 'unit_price' => 100,
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('proposta-comercial.print-batch', ['ids' => [$proposta->id]]));

        $response->assertOk();
        $response->assertSee('Item Selecionado Batch');
    }

    public function test_batch_print_combina_filtros_de_vendedor_e_intervalo_de_datas(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Batch Filtros '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Batch Filtros '.uniqid(), 'slug' => 'tenant-batch-filtros-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $vendedorAlvo = User::create([
            'name' => 'Vendedor Alvo', 'email' => 'vendedor-alvo-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $vendedorAlvo->forceFill(['email_verified_at' => now()])->save();

        $outroVendedor = User::create([
            'name' => 'Outro Vendedor', 'email' => 'outro-vendedor-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $outroVendedor->forceFill(['email_verified_at' => now()])->save();

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Batch Filtros']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras']);

        // Bate com todos os filtros: vendedor alvo + dentro do intervalo.
        $dentroDoIntervalo = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $vendedorAlvo->id,
        ]);
        $dentroDoIntervalo->forceFill(['created_at' => '2026-08-15'])->save();
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $dentroDoIntervalo->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Item Vendedor Alvo Dentro Intervalo', 'quantity' => 1, 'unit_price' => 100,
        ]);

        // Vendedor certo, mas fora do intervalo de datas -- não deve aparecer.
        $foraDoIntervalo = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $vendedorAlvo->id,
        ]);
        $foraDoIntervalo->forceFill(['created_at' => '2026-01-01'])->save();
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $foraDoIntervalo->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Item Vendedor Alvo Fora Intervalo', 'quantity' => 1, 'unit_price' => 100,
        ]);

        // Dentro do intervalo, mas de outro vendedor -- não deve aparecer.
        $outroVendedorDentroIntervalo = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $outroVendedor->id,
        ]);
        $outroVendedorDentroIntervalo->forceFill(['created_at' => '2026-08-15'])->save();
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $outroVendedorDentroIntervalo->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Item Outro Vendedor Dentro Intervalo', 'quantity' => 1, 'unit_price' => 100,
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('proposta-comercial.print-batch', [
            'vendedor_id' => $vendedorAlvo->id,
            'data_de' => '2026-08-01',
            'data_ate' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertSee('Item Vendedor Alvo Dentro Intervalo');
        $response->assertDontSee('Item Vendedor Alvo Fora Intervalo');
        $response->assertDontSee('Item Outro Vendedor Dentro Intervalo');
    }
}
