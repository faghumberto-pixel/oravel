<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use App\Filament\Central\Resources\ContratoResource\Widgets\ClientesPorPacoteStats;
use App\Filament\Pages\CrmFunil;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\CrmLeadResource;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AppProfile;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 6D-1: pacotes de contrato (Locadora, CRM, Estoque, Logística) com base comum e entrada por pacote. */
class CrmPacotesTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantComPacote(?string $pacote): Tenant
    {
        $features = $pacote ? array_fill_keys(Pacotes::features($pacote), true) : ['tabela_crm_leads' => true];
        $plan = Plan::create(['name' => 'Contrato '.uniqid(), 'pacote' => $pacote, 'price' => 100, 'base_price' => 100, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);

        return Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function admin(Tenant $t): User
    {
        $u = User::create(['name' => 'Admin '.uniqid(), 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    private function chavesDoGrupo(string $grupo): array
    {
        return array_keys(ContratoResource::groupedFeatureOptions()[$grupo] ?? []);
    }

    public function test_every_package_includes_the_common_base(): void
    {
        $base = array_merge($this->chavesDoGrupo('Configurações'), $this->chavesDoGrupo('Equipe'), ['modulo_dashboard']);
        $this->assertNotEmpty($this->chavesDoGrupo('Equipe'));

        foreach (array_keys(Pacotes::opcoes()) as $pacote) {
            $this->assertEmpty(array_diff($base, Pacotes::features($pacote)), "base ausente no pacote {$pacote}");
        }
    }

    public function test_crm_package_is_independent_of_the_erp(): void
    {
        $crm = Pacotes::features('vendas');

        $this->assertContains('tabela_crm_leads', $crm);
        $this->assertContains('tabela_crm_contas', $crm);
        $this->assertContains('tabela_crm_visitas', $crm);
        $this->assertContains('tabela_crm_propostas', $crm);

        // Nenhum módulo dos menus do ERP (ativos, manutenção, estoque, logística, frota, financeiro…) entra no CRM.
        $erp = collect(['Manutenção', 'Ativos', 'Materiais e Peças', 'Gestão de Frota', 'Logística', 'Financeiro', 'PMP', 'Itens Agregados', 'EPI', 'Conformidade NR-13', 'Departamento Pessoal', 'Relatórios'])
            ->flatMap(fn ($g) => $this->chavesDoGrupo($g))->all();
        $this->assertSame([], array_values(array_intersect($crm, $erp)), 'CRM não pode levar módulos do ERP');
        $this->assertNotContains('tabela_clients', $crm);
        $this->assertNotContains('tabela_contracts', $crm);
    }

    public function test_storage_and_logistics_packages_follow_their_menu_groups(): void
    {
        $estoque = Pacotes::features('estoque');
        $this->assertEmpty(array_diff($this->chavesDoGrupo('Materiais e Peças'), $estoque), 'todo módulo do menu entra');
        $this->assertEmpty(array_intersect($estoque, $this->chavesDoGrupo('Logística')));
        $this->assertNotContains('tabela_crm_leads', $estoque);

        $log = Pacotes::features('logistica');
        $this->assertEmpty(array_diff(array_merge($this->chavesDoGrupo('Logística'), $this->chavesDoGrupo('Gestão de Frota')), $log));
        $this->assertEmpty(array_intersect($log, $this->chavesDoGrupo('Materiais e Peças')));
    }

    public function test_locadora_is_the_complete_erp_and_contains_every_other_package(): void
    {
        $locadora = Pacotes::features('locadora');

        foreach (['vendas', 'estoque', 'logistica'] as $outro) {
            $this->assertEmpty(array_diff(Pacotes::features($outro), $locadora), "locadora deve conter {$outro}");
        }

        $this->assertContains('tabela_clients', $locadora);
        $this->assertContains('tabela_crm_leads', $locadora);
        $this->assertSame('Locadora', Pacotes::nome('locadora'));
        $this->assertSame('Personalizado', Pacotes::nome(null));
    }

    public function test_features_by_group_fill_each_contract_checkbox_list(): void
    {
        $porGrupo = [];
        foreach (array_keys(ContratoResource::groupedFeatureOptions()) as $grupo) {
            $porGrupo[$grupo] = Pacotes::featuresDoGrupo('vendas', $grupo);
        }

        $this->assertSame([], array_diff(collect($porGrupo)->flatten()->all(), Pacotes::features('vendas')));
        $this->assertContains('tabela_crm_leads', $porGrupo['Comercial']);
        $this->assertSame([], $porGrupo['Manutenção'] ?? []);
    }

    public function test_crm_only_tenant_sees_crm_but_not_the_erp(): void
    {
        $t = $this->tenantComPacote('vendas');
        $this->actingAs($this->admin($t));

        $this->assertTrue(CrmLeadResource::canViewAny());
        $this->assertFalse(ClientResource::canViewAny(), 'sem o módulo de Clientes do ERP');
        $this->assertSame('vendas', $t->fresh()->pacote());
        $this->assertSame('vendas', Pacotes::doTenant($t->fresh()));
    }

    public function test_home_goes_to_the_package_screen_and_locadora_keeps_the_default(): void
    {
        $crm = $this->tenantComPacote('vendas');
        $this->actingAs($this->admin($crm));
        $this->assertSame(CrmFunil::getUrl(), Pacotes::urlInicial($crm));
        $this->assertStringContainsString('crm-funil', AppProfile::homeUrl(auth()->user(), AppProfile::ADMIN));

        $locadora = $this->tenantComPacote('locadora');
        $this->actingAs($this->admin($locadora));
        $this->assertNull(Pacotes::urlInicial($locadora));
        $this->assertStringContainsString('painel-controle', AppProfile::homeUrl(auth()->user(), AppProfile::ADMIN));

        $personalizado = $this->tenantComPacote(null);
        $this->actingAs($this->admin($personalizado));
        $this->assertNull(Pacotes::urlInicial($personalizado));
        $this->assertNull(Pacotes::urlInicial(null));
    }

    public function test_admin_route_sends_a_crm_contract_straight_to_the_funnel(): void
    {
        $t = $this->tenantComPacote('vendas');
        $admin = $this->admin($t);

        $this->actingAs($admin)->get('/admin')->assertRedirect(CrmFunil::getUrl());
    }

    public function test_contract_form_stores_the_package_and_lists_filter_and_count_by_it(): void
    {
        $super = User::create(['name' => 'Super', 'email' => (config('oravel.super_admins')[0] ?? 'faghumberto@gmail.com'), 'password' => bcrypt('x'), 'is_approved' => true]);
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        Livewire::test(ContratoResource\Pages\CreateContrato::class)
            ->fillForm(['name' => 'Cliente só CRM', 'pacote' => 'vendas', 'base_price' => 199, 'billing_cycle' => 'monthly'])
            ->call('create')->assertHasNoFormErrors();

        // "Aplicar módulos do pacote" marca os grupos certos e não desmarca o que já estava marcado.
        $estado = [ContratoResource::groupFieldName('Manutenção') => ['tabela_equipment_damages']];
        ContratoResource::aplicarPacote('vendas', fn ($c) => $estado[$c] ?? null, function ($c, $v) use (&$estado) {
            $estado[$c] = $v;
        });
        $this->assertContains('tabela_crm_leads', $estado[ContratoResource::groupFieldName('Comercial')]);
        $this->assertSame(['tabela_equipment_damages'], $estado[ContratoResource::groupFieldName('Manutenção')], 'não desmarca nem acrescenta ERP');

        $plan = Plan::where('name', 'Cliente só CRM')->firstOrFail();
        $this->assertSame('vendas', $plan->pacote);

        $this->tenantComPacote('estoque');
        $this->tenantComPacote(null);
        $crmTenant = Tenant::create(['name' => 'Cliente CRM', 'slug' => 'c-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);

        Livewire::test(ContratoResource\Pages\ListContratos::class)->assertSuccessful()
            ->filterTable('pacote', 'vendas')->assertCanSeeTableRecords([$plan])
            ->filterTable('pacote', 'estoque')->assertCanNotSeeTableRecords([$plan]);

        $stats = Livewire::test(ClientesPorPacoteStats::class)->assertSuccessful();
        $stats->assertSee('Clientes — Vendas')->assertSee('Clientes — Estoque')->assertSee('Clientes — Personalizado');
        $this->assertNotNull($crmTenant);
    }

    public function test_migration_backfills_existing_contracts_as_locadora_and_is_reversible(): void
    {
        $plan = Plan::create(['name' => 'Antigo', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $migration = require base_path('database/migrations/2026_11_20_100000_add_pacote_to_plans.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('plans', 'pacote'));

        $migration->up();
        $this->assertSame('locadora', DB::table('plans')->where('id', $plan->id)->value('pacote'));
    }
}
