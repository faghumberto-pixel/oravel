<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Filament\Resources\MaintenanceOrderResource\Pages\CreateMaintenanceOrder;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Busca de ativos por placa (e demais códigos): lista, filtro e campos de escolher o ativo. */
class AssetBuscaPlacaTest extends TestCase
{
    use DatabaseTransactions;

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets', 'tabela_maintenance_orders']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function ativo(Tenant $tenant, array $extra = []): Asset
    {
        return Asset::create(array_merge(['tenant_id' => $tenant->id, 'patrimonio' => 'PAT-'.uniqid(), 'name' => 'Ativo '.uniqid(), 'tag' => 'TAG-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL, 'grupo' => Asset::GRUPO_MAQUINA], $extra));
    }

    public function test_pesquisa_acha_por_nome_patrimonio_tag_serie_placa_e_chassi_ignorando_hifen_e_maiusculas(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->ativo($tenant, ['name' => 'Caminhão Azul', 'patrimonio' => 'CAM-001', 'tag' => 'TG-77', 'serial_number' => 'SN-998', 'grupo' => Asset::GRUPO_VEICULO,
            'placa' => 'KMX1A23', 'chassi' => '9BWZZZ377VT004251']);
        $this->ativo($tenant, ['name' => 'Gerador']);

        foreach (['KMX1A23', 'kmx1a23', 'KMX-1A23', 'kmx 1a23', 'mx1a', 'Azul', 'CAM-001', 'tg-77', 'sn-998', '9bwzzz377', '9BWZZZ-377 VT'] as $busca) {
            $this->assertArrayHasKey($v->id, Asset::opcoesPesquisa($busca), "Busca \"{$busca}\" deveria achar o veículo.");
        }
        $this->assertSame([], Asset::opcoesPesquisa('ZZZ9Z99'));
        $this->assertSame('CAM-001 — Caminhão Azul (KMX1A23)', Asset::opcoesPesquisa('kmx-1a23')[$v->id]);
    }

    public function test_pesquisa_so_enxerga_os_ativos_do_proprio_cliente(): void
    {
        [$a, $adminA] = $this->cliente();
        [$b, $adminB] = $this->cliente();
        $vA = $this->ativo($a, ['grupo' => Asset::GRUPO_VEICULO, 'placa' => 'AAA1A11']);

        $this->actingAs($adminB);
        $this->assertSame([], Asset::opcoesPesquisa('AAA-1A11'));
        $this->actingAs($adminA);
        $this->assertArrayHasKey($vA->id, Asset::opcoesPesquisa('AAA-1A11'));
    }

    public function test_busca_da_lista_acha_a_placa_com_hifen_e_o_filtro_de_placa_funciona(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->ativo($tenant, ['grupo' => Asset::GRUPO_VEICULO, 'placa' => 'KMX1A23']);
        $outro = $this->ativo($tenant, ['grupo' => Asset::GRUPO_VEICULO, 'placa' => 'QWE9R87']);
        $maquina = $this->ativo($tenant);

        Livewire::test(ListAssets::class)->searchTable('kmx-1a23')->assertCanSeeTableRecords([$v])->assertCanNotSeeTableRecords([$outro, $maquina]);
        Livewire::test(ListAssets::class)->searchTable('KMX1A23')->assertCanSeeTableRecords([$v]);
        Livewire::test(ListAssets::class)->filterTable('placa', ['placa' => 'kmx-1a'])->assertCanSeeTableRecords([$v])->assertCanNotSeeTableRecords([$outro, $maquina]);
        Livewire::test(ListAssets::class)->filterTable('placa', ['placa' => null])->assertCanSeeTableRecords([$v, $outro, $maquina]);
    }

    public function test_campo_ativo_da_ordem_de_servico_busca_pela_placa(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->ativo($tenant, ['name' => 'Caminhão Azul', 'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'KMX1A23']);
        $this->ativo($tenant, ['name' => 'Gerador']);

        $campo = Livewire::test(CreateMaintenanceOrder::class)->instance()->form->getFlatFields()['asset_id'];

        $resultado = $campo->getSearchResults('kmx-1a23');
        $this->assertSame([$v->id], array_keys($resultado));
        $this->assertStringContainsString('(KMX1A23)', $resultado[$v->id]);
    }
}
