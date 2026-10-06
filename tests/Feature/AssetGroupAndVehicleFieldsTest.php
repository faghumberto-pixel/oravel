<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cadastro do ativo: "Máquina / Equipamento" mostra uns campos e "Veículo" mostra outros (placa, renavam,
 * chassi, odômetro, emplacamento, seguro; tacógrafo só em veículo pesado) -- 06/10/2026.
 */
class AssetGroupAndVehicleFieldsTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantWithAdmin(): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function asset(Tenant $tenant, array $extra = []): Asset
    {
        return Asset::create(array_merge(['tenant_id' => $tenant->id, 'name' => 'Ativo '.uniqid(), 'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL], $extra));
    }

    public function test_existing_and_new_assets_default_to_machine(): void
    {
        [$tenant] = $this->tenantWithAdmin();

        $asset = $this->asset($tenant)->fresh();

        $this->assertSame(Asset::GRUPO_MAQUINA, $asset->grupo);
        $this->assertFalse($asset->isVehicle());
        $this->assertFalse($asset->veiculo_pesado);
    }

    public function test_each_choice_shows_its_own_fields(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin();
        $this->actingAs($admin);
        $asset = $this->asset($tenant);

        $page = Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()]);
        // Campos ocultos saem da lista de campos do formulário.
        $fields = fn () => array_keys($page->instance()->form->getFlatFields());

        // Máquina: sem placa/renavam/odômetro/seguro; com horímetro.
        $page->fillForm(['grupo' => Asset::GRUPO_MAQUINA]);
        foreach (['modelo', 'cor', 'chassi', 'manufacturing_year', 'horimetro_inicial'] as $name) {
            $this->assertContains($name, $fields(), "Máquina deveria mostrar {$name}.");
        }
        foreach (['placa', 'renavam', 'odometro_atual', 'licenciamento_vencimento', 'ipva_vencimento', 'seguro_seguradora', 'seguro_vencimento', 'tacografo_numero'] as $name) {
            $this->assertNotContains($name, $fields(), "Máquina não deveria mostrar {$name}.");
        }

        // Veículo leve: placa, renavam, odômetro, emplacamento, seguro; sem horímetro nem tacógrafo.
        $page->fillForm(['grupo' => Asset::GRUPO_VEICULO, 'veiculo_pesado' => false]);
        foreach (['modelo', 'cor', 'placa', 'renavam', 'chassi', 'manufacturing_year', 'ano_modelo', 'odometro_atual', 'licenciamento_vencimento', 'ipva_vencimento', 'seguro_seguradora', 'seguro_apolice', 'seguro_vencimento'] as $name) {
            $this->assertContains($name, $fields(), "Veículo deveria mostrar {$name}.");
        }
        foreach (['horimetro_inicial', 'last_horimetro', 'tacografo_numero', 'tacografo_vencimento'] as $name) {
            $this->assertNotContains($name, $fields(), "Veículo leve não deveria mostrar {$name}.");
        }

        // Veículo pesado: aparece o tacógrafo.
        $page->fillForm(['grupo' => Asset::GRUPO_VEICULO, 'veiculo_pesado' => true]);
        $this->assertContains('tacografo_numero', $fields());
        $this->assertContains('tacografo_vencimento', $fields());
    }

    public function test_vehicle_saves_normalized_plate_and_valid_chassis(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin();
        $this->actingAs($admin);
        $asset = $this->asset($tenant);

        Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->fillForm([
                'patrimonio' => 'PAT-'.uniqid(), 'grupo' => Asset::GRUPO_VEICULO, 'veiculo_pesado' => true, 'modelo' => 'Actros 2651', 'cor' => 'Branca',
                'placa' => 'abc-1d23', 'renavam' => '12345678901', 'chassi' => '9bwzzz377vt004251',
                'manufacturing_year' => 2020, 'ano_modelo' => 2021, 'odometro_atual' => 154300,
                'licenciamento_vencimento' => '2026-12-31', 'seguro_seguradora' => 'Porto', 'seguro_apolice' => 'AP-1', 'seguro_vencimento' => '2027-03-01',
                'tacografo_numero' => 'TC-77', 'tacografo_vencimento' => '2027-05-10',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $asset->refresh();
        $this->assertSame('ABC1D23', $asset->placa);
        $this->assertSame('9BWZZZ377VT004251', $asset->chassi);
        $this->assertTrue($asset->isVehicle() && $asset->veiculo_pesado);
        $this->assertSame('2026-12-31', $asset->licenciamento_vencimento->toDateString());
        $this->assertSame('TC-77', $asset->tacografo_numero);
    }

    public function test_invalid_plate_and_chassis_are_rejected_and_plate_is_unique_per_tenant(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin();
        [$otherTenant] = $this->tenantWithAdmin();
        $this->asset($tenant, ['grupo' => Asset::GRUPO_VEICULO, 'placa' => 'XYZ9A87']);
        $this->asset($otherTenant, ['grupo' => Asset::GRUPO_VEICULO, 'placa' => 'QWE1R23']);
        $this->actingAs($admin);
        $asset = $this->asset($tenant);

        $page = fn (array $data) => Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->fillForm(array_merge(['patrimonio' => 'PAT-'.uniqid(), 'grupo' => Asset::GRUPO_VEICULO], $data))
            ->call('save');

        $page(['placa' => '12345'])->assertHasFormErrors(['placa']);
        $page(['placa' => 'xyz-9a87'])->assertHasFormErrors(['placa']);          // já existe no mesmo tenant
        $page(['placa' => 'QWE1R23'])->assertHasNoFormErrors();                    // outro tenant pode ter
        $page(['chassi' => '123'])->assertHasFormErrors(['chassi']);
        $page(['chassi' => '9BWZZZ377VT00425I'])->assertHasFormErrors(['chassi']); // letra I não vale
    }
}
