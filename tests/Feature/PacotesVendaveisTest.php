<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Pacotes vendáveis separadamente: Locadora, Vendas, Manutenção, Serviços, Estoque, Logística e Departamento Pessoal. */
class PacotesVendaveisTest extends TestCase
{
    use DatabaseTransactions;

    private function chavesDoGrupo(string $grupo): array
    {
        return array_keys(ContratoResource::groupedFeatureOptions()[$grupo] ?? []);
    }

    /** Módulos que só fazem sentido em locação de equipamentos. */
    private function chavesDeLocacao(): array
    {
        return ['tabela_solicitacao_locacao', 'tabela_equipment_damages', 'tabela_equipment_replacements'];
    }

    public function test_all_seven_packages_exist_with_names(): void
    {
        $this->assertSame(
            ['locadora', 'vendas', 'manutencao', 'servicos', 'estoque', 'logistica', 'departamento_pessoal'],
            array_keys(Pacotes::opcoes())
        );
        $this->assertSame('Manutenção', Pacotes::nome('manutencao'));
        $this->assertSame('Serviços', Pacotes::nome('servicos'));
        $this->assertSame('Vendas', Pacotes::nome('vendas'));
        $this->assertSame('Departamento Pessoal', Pacotes::nome('departamento_pessoal'));
    }

    public function test_hr_package_has_people_absences_time_clock_and_epi_but_nothing_operational(): void
    {
        $f = Pacotes::features('departamento_pessoal');

        foreach (['tabela_employees', 'tabela_job_roles', 'tabela_departments', 'tabela_absences', 'tabela_equipment_allocations', 'tabela_time_clocks', 'tabela_epi_deliveries', 'tabela_epi_entries', 'tabela_epi_specifications', 'menu_gestao_epi', 'modulo_dashboard', 'tabela_courses'] as $chave) {
            $this->assertContains($chave, $f, $chave);
        }

        $proibidas = array_merge(
            $this->chavesDoGrupo('Ativos'), $this->chavesDoGrupo('Manutenção'), $this->chavesDoGrupo('Financeiro'), $this->chavesDoGrupo('Materiais e Peças'),
            $this->chavesDoGrupo('Logística'), $this->chavesDoGrupo('Comercial'), $this->chavesDoGrupo('Complementos'),
            ['tabela_contracts', 'tabela_quotes', 'tabela_crm_leads', 'tabela_maintenance_orders'],
        );
        $this->assertSame([], array_values(array_intersect($f, $proibidas)));
    }

    public function test_hr_package_showcase_plan_is_born_inactive_and_without_price(): void
    {
        $plano = Pacotes::planoDeVitrine('departamento_pessoal');

        $this->assertFalse((bool) $plano->is_active);
        $this->assertNull(Pacotes::precoSugerido('departamento_pessoal'));
        $this->assertTrue((bool) ($plano->features['tabela_absences'] ?? false));
    }

    public function test_old_crm_code_is_an_alias_of_vendas(): void
    {
        $this->assertSame('vendas', Pacotes::normalizar('crm'));
        $this->assertSame('Vendas', Pacotes::nome('crm'));
        $this->assertSame(Pacotes::features('vendas'), Pacotes::features('crm'));

        $plan = Plan::create(['name' => 'Antigo '.uniqid(), 'pacote' => 'crm', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $this->assertSame('vendas', $tenant->fresh()->pacote());
    }

    public function test_maintenance_package_has_assets_orders_pmp_but_no_commercial_or_finance(): void
    {
        $f = Pacotes::features('manutencao');

        $this->assertContains('tabela_assets', $f);
        $this->assertContains('tabela_maintenance_orders', $f);
        $this->assertContains('tabela_clients', $f);
        foreach (['Ativos', 'Manutenção', 'PMP', 'Itens Agregados'] as $g) {
            $esperadas = array_diff($this->chavesDoGrupo($g), ['tabela_equipment_damages', 'tabela_equipment_replacements']);
            $this->assertSame([], array_values(array_diff($esperadas, $f)), "grupo {$g} inteiro (menos locação)");
        }

        $proibidas = array_merge(
            $this->chavesDoGrupo('Financeiro'),
            $this->chavesDoGrupo('Materiais e Peças'),
            $this->chavesDoGrupo('Logística'),
            $this->chavesDoGrupo('Gestão de Frota'),
            $this->chavesDeLocacao(),
            ['tabela_contracts', 'tabela_quotes', 'tabela_proposta_comercial', 'tabela_crm_leads'],
        );
        $this->assertSame([], array_values(array_intersect($f, $proibidas)));
    }

    public function test_services_package_has_orders_quotes_contracts_and_finance_but_no_rental(): void
    {
        $f = Pacotes::features('servicos');

        foreach (['tabela_clients', 'tabela_quotes', 'tabela_proposta_comercial', 'tabela_contracts', 'tabela_maintenance_orders', 'tabela_account_receivables'] as $chave) {
            $this->assertContains($chave, $f);
        }

        $proibidas = array_merge(
            $this->chavesDeLocacao(),
            $this->chavesDoGrupo('Materiais e Peças'),
            $this->chavesDoGrupo('Logística'),
            $this->chavesDoGrupo('Gestão de Frota'),
            $this->chavesDoGrupo('Departamento Pessoal'),
            ['tabela_crm_leads'],
        );
        $this->assertSame([], array_values(array_intersect($f, $proibidas)));
    }

    public function test_every_package_carries_the_common_base_and_locadora_contains_all(): void
    {
        $locadora = Pacotes::features('locadora');

        foreach (array_keys(Pacotes::opcoes()) as $codigo) {
            $this->assertContains('modulo_dashboard', Pacotes::features($codigo), $codigo);
            $this->assertSame([], array_values(array_diff(Pacotes::features($codigo), $locadora)), "locadora deve conter {$codigo}");
        }
    }

    public function test_package_features_all_exist_in_the_module_catalog(): void
    {
        $catalogo = collect(ContratoResource::groupedFeatureOptions())->flatMap(fn ($o) => array_keys($o))
            ->merge(['tabela_crm_etapas', 'tabela_crm_propostas'])->all();

        foreach (['manutencao', 'servicos'] as $codigo) {
            $desconhecidas = array_diff(Pacotes::features($codigo), $catalogo);
            $this->assertSame([], array_values($desconhecidas), "{$codigo} usa chave que não existe nos menus");
        }
    }

    public function test_rename_migration_is_reversible(): void
    {
        $migration = require base_path('database/migrations/2026_11_22_100000_renomear_pacote_crm_para_vendas.php');
        $crm = Plan::create(['name' => 'C '.uniqid(), 'pacote' => 'crm', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        $migration->up();
        $this->assertSame('vendas', DB::table('plans')->where('id', $crm->id)->value('pacote'));

        $migration->down();
        $this->assertSame('crm', DB::table('plans')->where('id', $crm->id)->value('pacote'));
    }

    public function test_price_comes_from_the_showcase_plan_registered_in_central(): void
    {
        $this->assertNull(Pacotes::precoSugerido('manutencao'), 'sem vitrine, sem preço');

        $vitrine = Pacotes::planoDeVitrine('manutencao');
        $this->assertNull(Pacotes::precoSugerido('manutencao'), 'vitrine nasce sem preço');
        $this->assertSame('monthly', Pacotes::cicloSugerido('manutencao'));

        $vitrine->update(['base_price' => 490, 'price' => 490, 'billing_cycle' => 'annual']);

        $this->assertSame(490.0, Pacotes::precoSugerido('manutencao'));
        $this->assertSame('annual', Pacotes::cicloSugerido('manutencao'));
    }

    public function test_choosing_a_package_suggests_the_central_price_only_when_the_field_is_empty(): void
    {
        Pacotes::planoDeVitrine('servicos')->update(['base_price' => 790, 'price' => 790, 'billing_cycle' => 'quarterly']);
        $super = User::create(['name' => 'Super', 'email' => (config('oravel.super_admins')[0] ?? 'faghumberto@gmail.com'), 'password' => bcrypt('x'), 'is_approved' => true]);
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Livewire::test(ContratoResource\Pages\CreateContrato::class)
            ->fillForm(['pacote' => 'servicos'])
            ->assertFormSet(['base_price' => 790, 'billing_cycle' => 'quarterly']);

        // Valor já digitado nunca é sobrescrito.
        Livewire::test(ContratoResource\Pages\CreateContrato::class)
            ->fillForm(['base_price' => 1000])
            ->fillForm(['pacote' => 'servicos'])
            ->assertFormSet(['base_price' => 1000]);
    }
}
