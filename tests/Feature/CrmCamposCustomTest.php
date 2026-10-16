<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\CrmCampoCustomResource\Pages\ManageCrmCampoCustoms;
use App\Filament\Resources\CrmLeadResource;
use App\Models\Client;
use App\Models\CrmCampoCustom;
use App\Models\CrmLead;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CrmCamposCustom;
use Filament\Forms;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 2 do CRM: campos personalizados em leads e clientes. */
class CrmCamposCustomTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        CrmCamposCustom::esquecer();
    }

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Campos '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_crm_leads' => true, 'tabela_clients' => true, 'tabela_crm_campos_custom' => true, 'tabela_crm_funis' => true],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function campo(Tenant $t, string $entidade, string $nome, string $tipo, array $extra = []): CrmCampoCustom
    {
        return CrmCampoCustom::create(['tenant_id' => $t->id, 'entidade' => $entidade, 'nome' => $nome, 'tipo' => $tipo] + $extra);
    }

    public function test_key_is_generated_from_name_and_unique_per_tenant_and_entity(): void
    {
        [$a] = $this->makeTenantAdmin();
        [$b] = $this->makeTenantAdmin();

        $c1 = $this->campo($a, 'lead', 'Frota Própria', 'numero');
        $c2 = $this->campo($a, 'lead', 'Frota Própria', 'numero');
        $c3 = $this->campo($a, 'cliente', 'Frota Própria', 'numero');
        $c4 = $this->campo($b, 'lead', 'Frota Própria', 'numero');

        $this->assertSame('frota_propria', $c1->chave);
        $this->assertSame('frota_propria_2', $c2->chave);
        $this->assertSame('frota_propria', $c3->chave, 'outra entidade pode repetir');
        $this->assertSame('frota_propria', $c4->chave, 'outro tenant pode repetir');
    }

    public function test_builder_creates_one_component_per_type_with_required_and_options(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $this->campo($tenant, 'lead', 'Obs', 'texto_longo');
        $this->campo($tenant, 'lead', 'Qtd', 'numero', ['obrigatorio' => true]);
        $this->campo($tenant, 'lead', 'Verba', 'moeda');
        $this->campo($tenant, 'lead', 'Visita', 'data');
        $this->campo($tenant, 'lead', 'Parceiro', 'sim_nao');
        $this->campo($tenant, 'lead', 'Porte', 'lista', ['opcoes' => ['P', 'M', 'G']]);
        $this->campo($tenant, 'lead', 'Frentes', 'lista_multipla', ['opcoes' => ['A', 'B']]);

        $componentes = collect(CrmCamposCustom::componentes('lead'))->keyBy(fn ($c) => $c->getName());

        $this->assertInstanceOf(Forms\Components\Textarea::class, $componentes['custom.obs']);
        $this->assertTrue($componentes['custom.qtd']->isRequired());
        $this->assertInstanceOf(Forms\Components\DatePicker::class, $componentes['custom.visita']);
        $this->assertInstanceOf(Forms\Components\Toggle::class, $componentes['custom.parceiro']);
        $this->assertSame(['P' => 'P', 'M' => 'M', 'G' => 'G'], $componentes['custom.porte']->getOptions());
        $this->assertTrue($componentes['custom.frentes']->isMultiple());
        $this->assertCount(7, $componentes);
        $this->assertNotNull(CrmCamposCustom::secao('lead'));
        $this->assertNull(CrmCamposCustom::secao('cliente'), 'sem campos de cliente não há seção');
    }

    public function test_lead_form_saves_values_and_enforces_required(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->campo($tenant, 'lead', 'Frota', 'numero', ['obrigatorio' => true]);
        $this->campo($tenant, 'lead', 'Porte', 'lista', ['opcoes' => ['P', 'G']]);
        $this->actingAs($admin);

        Livewire::test(CrmLeadResource\Pages\CreateCrmLead::class)
            ->fillForm(['name' => 'Sem frota', 'custom' => ['porte' => 'G']])
            ->call('create')
            ->assertHasFormErrors(['custom.frota']);

        Livewire::test(CrmLeadResource\Pages\CreateCrmLead::class)
            ->fillForm(['name' => 'Com frota', 'custom' => ['frota' => 12, 'porte' => 'G']])
            ->call('create')
            ->assertHasNoFormErrors();

        $lead = CrmLead::where('name', 'Com frota')->firstOrFail();
        $this->assertEquals(12, $lead->custom['frota']);
        $this->assertSame('G', $lead->custom['porte']);
    }

    public function test_client_form_has_the_custom_tab_and_saves_values(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->campo($tenant, 'cliente', 'Obras ativas', 'numero');
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente X']);

        Livewire::test(ClientResource\Pages\EditClient::class, ['record' => $client->getKey()])
            ->assertSee('Campos Personalizados')
            ->fillForm(['custom' => ['obras_ativas' => 5]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(5, $client->fresh()->custom['obras_ativas']);
    }

    public function test_normalizar_drops_unknown_keys_coerces_types_and_keeps_inactive_values(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $this->campo($tenant, 'lead', 'Qtd', 'numero');
        $this->campo($tenant, 'lead', 'Visita', 'data');
        $this->campo($tenant, 'lead', 'Parceiro', 'sim_nao');
        $this->campo($tenant, 'lead', 'Porte', 'lista', ['opcoes' => ['P', 'G']]);
        $inativo = $this->campo($tenant, 'lead', 'Antigo', 'texto', ['ativo' => false]);

        $saida = CrmCamposCustom::normalizar('lead', [
            'qtd' => '7', 'visita' => '2026-10-20', 'parceiro' => false, 'porte' => 'XG',
            'desconhecido' => 'x', 'antigo' => 'guardado', 'qtd_vazia' => '',
        ], $tenant->id);

        $this->assertEquals(7, $saida['qtd']);
        $this->assertSame('2026-10-20', $saida['visita']);
        $this->assertFalse($saida['parceiro']);
        $this->assertArrayNotHasKey('porte', $saida, 'opção fora da lista é descartada');
        $this->assertArrayNotHasKey('desconhecido', $saida);
        $this->assertSame('guardado', $saida[$inativo->chave], 'campo inativo mantém o valor');
        $this->assertSame(['qtd' => 7, 'visita' => '2026-10-20', 'parceiro' => false, 'antigo' => 'guardado'], $saida + []);
    }

    public function test_other_tenants_fields_and_values_never_leak(): void
    {
        [$a, $adminA] = $this->makeTenantAdmin();
        [$b] = $this->makeTenantAdmin();
        $this->campo($b, 'lead', 'Segredo B', 'texto');
        $this->campo($a, 'lead', 'Meu campo', 'texto');
        $this->actingAs($adminA);

        $nomes = collect(CrmCamposCustom::componentes('lead'))->map(fn ($c) => $c->getName())->all();
        $this->assertSame(['custom.meu_campo'], $nomes);

        // Valor gravado com chave de outro tenant é descartado.
        $lead = CrmLead::create(['tenant_id' => $a->id, 'name' => 'L', 'custom' => ['segredo_b' => 'x', 'meu_campo' => 'ok']]);
        $this->assertSame(['meu_campo' => 'ok'], $lead->fresh()->custom);

        $this->assertSame(0, CrmCamposCustom::campos('lead', true, (string) Str::uuid())->count(), 'tenant sem campos não herda os de ninguém');
    }

    public function test_convert_to_client_copies_only_same_key_and_same_type(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        $this->campo($tenant, 'lead', 'Frota', 'numero');
        $this->campo($tenant, 'cliente', 'Frota', 'numero');
        $this->campo($tenant, 'lead', 'Porte', 'texto');
        $this->campo($tenant, 'cliente', 'Porte', 'data');   // mesmo nome, tipo diferente
        $this->campo($tenant, 'lead', 'Só lead', 'texto');

        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L', 'custom' => ['frota' => 9, 'porte' => 'G', 'so_lead' => 'x']]);
        $client = $lead->convertToClient();

        $this->assertEquals(['frota' => 9], $client->fresh()->custom);
    }

    public function test_resource_creates_field_blocks_delete_when_in_use_and_locks_type(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(ManageCrmCampoCustoms::class)
            ->callAction('create', ['nome' => 'Frota', 'entidade' => 'lead', 'tipo' => 'numero', 'ordem' => 1, 'ativo' => true])
            ->assertHasNoActionErrors();

        $campo = CrmCampoCustom::where('chave', 'frota')->firstOrFail();
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Usa', 'custom' => ['frota' => 3]]);
        $this->assertSame(1, $campo->emUso());

        Livewire::test(ManageCrmCampoCustoms::class)->callTableAction('delete', $campo);
        $this->assertNotNull(CrmCampoCustom::find($campo->id), 'em uso: não exclui');

        CrmLead::where('name', 'Usa')->update(['custom' => []]);
        Livewire::test(ManageCrmCampoCustoms::class)->callTableAction('delete', $campo->fresh());
        $this->assertNull(CrmCampoCustom::find($campo->id), 'sem uso: exclui');
    }

    public function test_migration_adds_custom_columns_and_is_reversible(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Antigo']);
        $migration = require base_path('database/migrations/2026_11_13_100000_create_crm_campos_custom.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('crm_campos_custom'));
        $this->assertFalse(Schema::hasColumn('crm_leads', 'custom'));
        $this->assertFalse(Schema::hasColumn('clients', 'custom'));
        $this->assertSame('Antigo', DB::table('crm_leads')->where('id', $lead->id)->value('name'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('crm_leads', 'custom'));
        $this->assertSame('{}', DB::table('crm_leads')->where('id', $lead->id)->value('custom'), 'default vazio');
    }
}
