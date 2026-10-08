<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Models\Asset;
use App\Models\AssetModel;
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
        foreach (['modelo', 'cor', 'chassi', 'manufacturing_year', 'horimetro_inicial', 'placa'] as $name) {
            $this->assertContains($name, $fields(), "Máquina deveria mostrar {$name}.");
        }
        foreach (['renavam', 'odometro_atual', 'licenciamento_vencimento', 'ipva_vencimento', 'seguro_seguradora', 'seguro_vencimento', 'seguro_franquia_colisao', 'seguro_valor_cobertura', 'tacografo_numero'] as $name) {
            $this->assertNotContains($name, $fields(), "Máquina não deveria mostrar {$name}.");
        }

        // Veículo leve: placa, renavam, odômetro, km de aquisição (mesma coluna do horímetro de aquisição), emplacamento, seguro; sem leitura de horímetro nem tacógrafo.
        $page->fillForm(['grupo' => Asset::GRUPO_VEICULO, 'veiculo_pesado' => false]);
        foreach (['modelo', 'cor', 'placa', 'renavam', 'chassi', 'manufacturing_year', 'ano_modelo', 'odometro_atual', 'horimetro_inicial', 'licenciamento_vencimento', 'ipva_vencimento', 'seguro_seguradora', 'seguro_apolice', 'seguro_vencimento', 'seguro_valor_cobertura', 'seguro_cobertura_terceiros', 'seguro_franquia_colisao', 'seguro_franquia_vidros'] as $name) {
            $this->assertContains($name, $fields(), "Veículo deveria mostrar {$name}.");
        }
        foreach (['last_horimetro', 'tacografo_numero', 'tacografo_vencimento'] as $name) {
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

    public function test_vehicle_has_no_capacity_and_its_unit_is_always_km(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin();
        $this->actingAs($admin);
        // Ativo antigo com capacidade de máquina; ao virar veículo a unidade passa a Km.
        $asset = $this->asset($tenant, ['capacity_value' => 250, 'capacity_unit' => 'kVA']);

        $page = Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()]);
        $fields = fn () => array_keys($page->instance()->form->getFlatFields());

        $page->fillForm(['grupo' => Asset::GRUPO_MAQUINA]);
        $this->assertContains('capacity_value', $fields());
        $this->assertContains('capacity_unit', $fields());

        $page->fillForm(['patrimonio' => 'PAT-'.uniqid(), 'grupo' => Asset::GRUPO_VEICULO, 'capacity_unit' => 'kVA']);
        $this->assertNotContains('capacity_value', $fields(), 'Veículo não tem capacidade.');

        $page->call('save')->assertHasNoFormErrors();
        $this->assertSame('km', $asset->fresh()->capacity_unit);
    }

    public function test_model_field_uses_a_standardized_catalog_with_a_plus_button(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin();
        [$other, $otherAdmin] = $this->tenantWithAdmin();
        AssetModel::create(['tenant_id' => $tenant->id, 'name' => 'Actros 2651']);
        AssetModel::create(['tenant_id' => $other->id, 'name' => 'Modelo do outro cliente']);
        $this->actingAs($admin);
        $asset = $this->asset($tenant, ['grupo' => Asset::GRUPO_VEICULO, 'modelo' => 'texto antigo livre']);

        $page = Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()]);
        $field = $page->instance()->form->getFlatFields()['modelo'];

        $options = $field->getOptions();
        $this->assertArrayHasKey('Actros 2651', $options);
        $this->assertArrayHasKey('texto antigo livre', $options);          // modelo antigo continua aparecendo
        $this->assertArrayNotHasKey('Modelo do outro cliente', $options);   // catálogo é por cliente
        $this->assertTrue($field->hasCreateOptionActionFormSchema(), 'Falta o "+" para cadastrar modelo.');

        // "+": cadastra já padronizado (espaços) e não aceita repetido.
        $created = $field->evaluate($field->getCreateOptionUsing(), ['data' => ['name' => '  Volvo   FH 540 ', 'fabricante' => 'Volvo']]);
        $this->assertSame('Volvo FH 540', $created);
        $this->assertSame(1, AssetModel::where('name', 'Volvo FH 540')->count());
    }

    public function test_acquisition_field_is_km_for_vehicles_and_hours_for_machines(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin();
        $this->actingAs($admin);
        $asset = $this->asset($tenant);

        $page = Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()]);
        $campo = fn () => $page->instance()->form->getFlatFields()['horimetro_inicial'];

        $page->fillForm(['grupo' => Asset::GRUPO_MAQUINA]);
        $this->assertSame('Horímetro de Aquisição', $campo()->getLabel());
        $this->assertSame('h', $campo()->getSuffixLabel());

        $page->fillForm(['grupo' => Asset::GRUPO_VEICULO]);
        $this->assertSame('Km de Aquisição', $campo()->getLabel());
        $this->assertSame('km', $campo()->getSuffixLabel());
    }

    public function test_vehicle_saves_acquisition_km_and_list_shows_plate_and_odometer(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin();
        $this->actingAs($admin);
        $asset = $this->asset($tenant);

        Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->fillForm(['patrimonio' => 'PAT-'.uniqid(), 'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'kmx-1a23', 'horimetro_inicial' => 85000, 'odometro_atual' => 91500])
            ->call('save')
            ->assertHasNoFormErrors();

        $asset->refresh();
        $this->assertSame('KMX1A23', $asset->placa);
        $this->assertSame(85000.0, (float) $asset->horimetro_inicial);

        Livewire::test(ListAssets::class)
            ->assertSee('Placa: KMX1A23')
            ->assertSee('91.500 km');
    }

    public function test_vehicle_saves_insurance_values(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin();
        $this->actingAs($admin);
        $asset = $this->asset($tenant);

        Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->fillForm([
                'patrimonio' => 'PAT-'.uniqid(), 'grupo' => Asset::GRUPO_VEICULO, 'seguro_seguradora' => 'Porto', 'seguro_apolice' => 'AP-9',
                'seguro_valor_cobertura' => 85000.5, 'seguro_cobertura_terceiros' => 300000, 'seguro_franquia_colisao' => 4200, 'seguro_franquia_vidros' => 650.75,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $asset->refresh();
        $this->assertSame('85000.50', $asset->seguro_valor_cobertura);
        $this->assertSame('300000.00', $asset->seguro_cobertura_terceiros);
        $this->assertSame('4200.00', $asset->seguro_franquia_colisao);
        $this->assertSame('650.75', $asset->seguro_franquia_vidros);

        Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->fillForm(['seguro_franquia_colisao' => -10])
            ->call('save')
            ->assertHasFormErrors(['seguro_franquia_colisao']);
    }

    public function test_machine_has_a_free_format_plate_that_is_searchable_like_vehicle_plates(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin();
        [$other] = $this->tenantWithAdmin();
        $this->actingAs($admin);
        $this->asset($other, ['placa' => 'PLQ-9988']);   // placa igual em outra empresa não atrapalha (gravada como veio)
        $asset = $this->asset($tenant);

        $page = Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()]);
        $campo = fn () => $page->instance()->form->getFlatFields()['placa'];
        $page->fillForm(['grupo' => Asset::GRUPO_MAQUINA]);
        $this->assertSame('Placa do equipamento', $campo()->getLabel());
        $page->fillForm(['grupo' => Asset::GRUPO_VEICULO]);
        $this->assertSame('Placa', $campo()->getLabel());

        // Máquina aceita formato livre (não precisa ser ABC1234) e grava normalizada.
        Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->fillForm(['patrimonio' => 'PAT-'.uniqid(), 'grupo' => Asset::GRUPO_MAQUINA, 'placa' => 'plq-9988/a'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('PLQ9988A', $asset->fresh()->placa);

        // Achada pela pesquisa, pelo filtro e pelo campo de escolher o ativo, com ou sem hífen.
        $this->assertArrayHasKey($asset->id, Asset::opcoesPesquisa('plq-9988'));
        $this->assertStringContainsString('(PLQ9988A)', Asset::opcoesPesquisa('PLQ9988A')[$asset->id]);
        $outra = $this->asset($tenant, ['name' => 'Outra máquina']);
        Livewire::test(ListAssets::class)->searchTable('plq 9988-a')->assertCanSeeTableRecords([$asset])->assertCanNotSeeTableRecords([$outra]);
        Livewire::test(ListAssets::class)->filterTable('placa', ['placa' => 'plq-99'])->assertCanSeeTableRecords([$asset])->assertCanNotSeeTableRecords([$outra]);
        Livewire::test(ListAssets::class)->assertSee('Placa: PLQ9988A');
    }

    public function test_machine_plate_must_be_unique_in_the_company_and_have_a_valid_size(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin();
        $this->actingAs($admin);
        $this->asset($tenant, ['placa' => 'ABC1D23', 'grupo' => Asset::GRUPO_VEICULO]);
        $this->asset($tenant, ['placa' => 'PLQ777']);
        $asset = $this->asset($tenant);

        $salvar = fn (string $placa) => Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->fillForm(['patrimonio' => 'PAT-'.uniqid(), 'grupo' => Asset::GRUPO_MAQUINA, 'placa' => $placa])
            ->call('save');

        $salvar('plq-777')->assertHasFormErrors(['placa']);                 // já existe (outra máquina)
        $salvar('abc1d23')->assertHasFormErrors(['placa']);                 // já existe (um veículo)
        $salvar('x')->assertHasFormErrors(['placa']);                       // curta demais
        $salvar(str_repeat('A', 22))->assertHasFormErrors(['placa']);       // longa demais
        $salvar('PLQ-888')->assertHasNoFormErrors();
        $this->assertSame('PLQ888', $asset->fresh()->placa);
    }
}
