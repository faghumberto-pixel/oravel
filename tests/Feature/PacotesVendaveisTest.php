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

/** Pacotes vendáveis separadamente: Locadora, Vendas, Manutenção, Serviços, Estoque e Logística. */
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

    public function test_all_six_packages_exist_with_names(): void
    {
        $this->assertSame(
            ['locadora', 'vendas', 'manutencao', 'servicos', 'estoque', 'logistica'],
            array_keys(Pacotes::opcoes())
        );
        $this->assertSame('Manutenção', Pacotes::nome('manutencao'));
        $this->assertSame('Serviços', Pacotes::nome('servicos'));
        $this->assertSame('Vendas', Pacotes::nome('vendas'));
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

    public function test_price_suggestion_is_null_until_defined_and_reads_the_config(): void
    {
        $this->assertNull(Pacotes::precoSugerido('manutencao'));
        $this->assertSame('monthly', Pacotes::cicloSugerido('manutencao'));

        config(['pacotes.pacotes.manutencao.preco_base' => 490, 'pacotes.pacotes.manutencao.ciclo' => 'annual']);

        $this->assertSame(490.0, Pacotes::precoSugerido('manutencao'));
        $this->assertSame('annual', Pacotes::cicloSugerido('manutencao'));
        $this->assertSame(490.0, Pacotes::precoSugerido('manutencao'));
    }

    public function test_choosing_a_package_suggests_price_only_when_the_field_is_empty(): void
    {
        config(['pacotes.pacotes.servicos.preco_base' => 790, 'pacotes.pacotes.servicos.ciclo' => 'quarterly']);
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
