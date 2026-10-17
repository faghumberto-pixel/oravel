<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmContaResource;
use App\Filament\Resources\CrmContaResource\RelationManagers\ContatosRelationManager;
use App\Filament\Resources\CrmLeadResource;
use App\Filament\Resources\CrmLeadResource\Pages\EditCrmLead;
use App\Filament\Resources\CrmLeadResource\RelationManagers\ItensRelationManager;
use App\Filament\Resources\CrmProdutoResource\Pages\ManageCrmProdutos;
use App\Models\Client;
use App\Models\CrmCampoCustom;
use App\Models\CrmConta;
use App\Models\CrmContaContato;
use App\Models\CrmEtapa;
use App\Models\CrmLead;
use App\Models\CrmLeadContact;
use App\Models\CrmLeadItem;
use App\Models\CrmProduto;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CrmFunilPadrao;
use App\Services\CrmFunilService;
use App\Support\CrmCamposCustom;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 6A: CRM com vida própria (contas, catálogo, ganho que encerra no CRM, envio opcional ao ERP). */
class CrmContasCatalogoTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '11.222.333/0001-81';

    protected function setUp(): void
    {
        parent::setUp();
        CrmCamposCustom::esquecer();
    }

    /** Contrato só de CRM por padrão (sem Clientes/Ativos do ERP). */
    private function makeTenantAdmin(bool $comErp = false): array
    {
        $features = ['tabela_crm_leads' => true, 'tabela_crm_contas' => true, 'tabela_crm_produtos' => true, 'tabela_crm_campos_custom' => true];
        $comErp && $features['tabela_clients'] = true;

        $plan = Plan::create(['name' => 'Plano CRM '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function etapa(Tenant $t, string $slug)
    {
        CrmFunilPadrao::garantir($t->id);

        return CrmEtapa::withoutGlobalScopes()->where('tenant_id', $t->id)->where('slug', $slug)->firstOrFail();
    }

    public function test_account_document_is_unique_per_tenant_and_only_digits_count(): void
    {
        [$a] = $this->makeTenantAdmin();
        [$b] = $this->makeTenantAdmin();

        $conta = CrmConta::create(['tenant_id' => $a->id, 'nome' => 'Acme', 'documento' => self::CNPJ]);
        $this->assertSame('11222333000181', $conta->documento_digitos);
        $this->assertSame('pj', $conta->tipo_pessoa);
        $this->assertSame('pf', CrmConta::create(['tenant_id' => $a->id, 'nome' => 'Pessoa', 'documento' => '123.456.789-09'])->tipo_pessoa);

        CrmConta::create(['tenant_id' => $b->id, 'nome' => 'Outro tenant', 'documento' => '11222333000181']);   // permitido: outro tenant
        CrmConta::create(['tenant_id' => $a->id, 'nome' => 'Sem doc 1']);
        CrmConta::create(['tenant_id' => $a->id, 'nome' => 'Sem doc 2']);                                      // sem documento pode repetir

        $this->expectException(QueryException::class);
        CrmConta::create(['tenant_id' => $a->id, 'nome' => 'Repetida', 'documento' => '11222333000181']);
    }

    public function test_account_resource_crud_custom_fields_and_tenant_isolation(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$outro] = $this->makeTenantAdmin();
        CrmCampoCustom::create(['tenant_id' => $tenant->id, 'entidade' => 'conta', 'nome' => 'Frota', 'tipo' => 'numero']);
        CrmConta::create(['tenant_id' => $outro->id, 'nome' => 'Conta Secreta']);
        $this->actingAs($admin);

        Livewire::test(CrmContaResource\Pages\CreateCrmConta::class)
            ->fillForm(['nome' => 'Acme Locações', 'documento' => self::CNPJ, 'custom' => ['frota' => 7]])
            ->call('create')->assertHasNoFormErrors();

        $conta = CrmConta::where('nome', 'Acme Locações')->firstOrFail();
        $this->assertEquals(7, $conta->custom['frota']);

        Livewire::test(CrmContaResource\Pages\CreateCrmConta::class)
            ->fillForm(['nome' => 'Duplicada', 'documento' => '11222333000181'])
            ->call('create')->assertHasFormErrors(['documento']);

        Livewire::test(CrmContaResource\Pages\ListCrmContas::class)->assertSee('Acme Locações')->assertDontSee('Conta Secreta');
    }

    public function test_account_contacts_have_a_single_primary(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $conta = CrmConta::create(['tenant_id' => $tenant->id, 'nome' => 'Acme']);
        $this->actingAs($admin);

        $rm = fn () => Livewire::test(ContatosRelationManager::class, ['ownerRecord' => $conta, 'pageClass' => CrmContaResource\Pages\EditCrmConta::class]);
        $rm()->callTableAction('create', data: ['name' => 'Maria', 'is_primary' => true])->assertHasNoTableActionErrors();
        $rm()->callTableAction('create', data: ['name' => 'João', 'is_primary' => true])->assertHasNoTableActionErrors();

        $this->assertSame(['João'], CrmContaContato::where('crm_conta_id', $conta->id)->where('is_primary', true)->pluck('name')->all());
    }

    public function test_catalog_crud_unique_code_per_tenant_and_in_use_protection(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$outro] = $this->makeTenantAdmin();
        CrmProduto::create(['tenant_id' => $outro->id, 'nome' => 'Do outro', 'codigo' => 'SRV-1']);
        $this->actingAs($admin);

        Livewire::test(ManageCrmProdutos::class)
            ->callAction('create', ['nome' => 'Locação de gerador', 'codigo' => 'SRV-1', 'tipo' => 'servico', 'unidade' => 'dia', 'preco' => 1500, 'ativo' => true])
            ->assertHasNoActionErrors();   // mesmo código em outro tenant é permitido

        Livewire::test(ManageCrmProdutos::class)
            ->callAction('create', ['nome' => 'Repetido', 'codigo' => 'SRV-1', 'tipo' => 'produto', 'unidade' => 'un', 'preco' => 1, 'ativo' => true])
            ->assertHasActionErrors(['codigo']);

        $produto = CrmProduto::where('codigo', 'SRV-1')->where('tenant_id', $tenant->id)->firstOrFail();
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L']);
        CrmLeadItem::create(['tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'crm_produto_id' => $produto->id, 'quantidade' => 1]);

        Livewire::test(ManageCrmProdutos::class)->callTableAction('delete', $produto);
        $this->assertNotNull(CrmProduto::find($produto->id), 'produto em uso não é excluído');
    }

    public function test_lead_items_use_catalog_price_sum_and_can_set_the_estimated_value(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        $gerador = CrmProduto::create(['tenant_id' => $tenant->id, 'nome' => 'Gerador', 'preco' => 1500]);
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L']);

        $item = CrmLeadItem::create(['tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'crm_produto_id' => $gerador->id, 'quantidade' => 3]);
        $this->assertSame('Gerador', $item->descricao);
        $this->assertEquals(1500.0, $item->preco_unitario, 'preço vem do catálogo');
        $this->assertEquals(4500.0, $item->subtotal);
        CrmLeadItem::create(['tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'descricao' => 'Frete', 'quantidade' => 1, 'preco_unitario' => 200]);

        Livewire::test(ItensRelationManager::class, ['ownerRecord' => $lead, 'pageClass' => EditCrmLead::class])
            ->assertSee('Gerador')->assertSee('Frete')->callTableAction('usarComoValor');

        $this->assertEquals(4700.0, $lead->fresh()->estimated_value);
    }

    public function test_winning_a_lead_creates_the_account_and_ends_the_process_in_the_crm(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Maria', 'company_name' => 'Acme Ltda', 'document' => self::CNPJ, 'email' => 'm@acme.com', 'phone' => '1933334444', 'city' => 'Campinas', 'uf' => 'SP']);
        CrmLeadContact::create(['tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'name' => 'Maria', 'is_primary' => true]);
        CrmLeadContact::create(['tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'name' => 'João']);

        $this->assertNull($lead->fresh()->crm_conta_id, 'ainda aberto: sem conta');

        app(CrmFunilService::class)->moverLead($lead, $this->etapa($tenant, 'convertido'), $admin);

        $lead->refresh();
        $conta = CrmConta::findOrFail($lead->crm_conta_id);
        $this->assertSame('Acme Ltda', $conta->nome);
        $this->assertSame('11222333000181', $conta->documento_digitos);
        $this->assertSame(['Maria', 'João'], $conta->contatos->pluck('name')->all());
        $this->assertNull($lead->client_id, 'nada foi criado no ERP');
        $this->assertSame(0, Client::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        // Idempotente: repetir não duplica.
        $lead->garantirConta();
        $this->assertSame(1, CrmConta::where('tenant_id', $tenant->id)->count());

        // Segundo lead com o mesmo documento reaproveita a conta.
        $outro = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Outro', 'document' => '11222333000181', 'stage' => 'convertido']);
        $this->assertSame($conta->id, $outro->fresh()->crm_conta_id);
        $this->assertSame(1, CrmConta::where('tenant_id', $tenant->id)->count());
    }

    public function test_lead_custom_values_follow_to_the_account_by_same_key_and_type(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        CrmCampoCustom::create(['tenant_id' => $tenant->id, 'entidade' => 'lead', 'nome' => 'Frota', 'tipo' => 'numero']);
        CrmCampoCustom::create(['tenant_id' => $tenant->id, 'entidade' => 'conta', 'nome' => 'Frota', 'tipo' => 'numero']);
        CrmCampoCustom::create(['tenant_id' => $tenant->id, 'entidade' => 'lead', 'nome' => 'Porte', 'tipo' => 'texto']);
        CrmCampoCustom::create(['tenant_id' => $tenant->id, 'entidade' => 'conta', 'nome' => 'Porte', 'tipo' => 'data']);

        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L', 'stage' => 'convertido', 'custom' => ['frota' => 9, 'porte' => 'G']]);

        $this->assertEquals(['frota' => 9], $lead->fresh()->conta->custom);
    }

    public function test_send_to_erp_is_only_for_tenants_with_the_module_and_is_idempotent(): void
    {
        [$tenantSemErp, $adminSem] = $this->makeTenantAdmin();
        [$tenantComErp, $adminCom] = $this->makeTenantAdmin(comErp: true);

        $this->actingAs($adminSem);
        $this->assertFalse(CrmLeadResource::temErp());

        $this->actingAs($adminCom);
        $this->assertTrue(CrmLeadResource::temErp());

        CrmCampoCustom::create(['tenant_id' => $tenantComErp->id, 'entidade' => 'conta', 'nome' => 'Frota', 'tipo' => 'numero']);
        CrmCampoCustom::create(['tenant_id' => $tenantComErp->id, 'entidade' => 'cliente', 'nome' => 'Frota', 'tipo' => 'numero']);
        $conta = CrmConta::create(['tenant_id' => $tenantComErp->id, 'nome' => 'Acme', 'documento' => self::CNPJ, 'email' => 'a@acme.com', 'custom' => ['frota' => 4]]);
        CrmContaContato::create(['tenant_id' => $tenantComErp->id, 'crm_conta_id' => $conta->id, 'name' => 'Maria', 'is_primary' => true]);

        $client = $conta->enviarParaErp();
        $again = $conta->fresh()->enviarParaErp();

        $this->assertSame($client->id, $again->id, 'não duplica o Cliente');
        $this->assertSame($client->id, $conta->fresh()->client_id);
        $this->assertSame('Acme', $client->name);
        $this->assertSame('Maria', $client->contacts->first()->name);
        $this->assertSame('Maria', $client->contact_name);
        $this->assertEquals(['frota' => 4], $client->fresh()->custom);
        $this->assertSame(1, Client::withoutGlobalScopes()->where('tenant_id', $tenantComErp->id)->count());
    }

    public function test_crm_only_tenant_can_run_the_whole_flow_without_touching_clients(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        $antes = Client::withoutGlobalScopes()->count();

        $produto = CrmProduto::create(['tenant_id' => $tenant->id, 'nome' => 'Consultoria', 'preco' => 500]);
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead', 'company_name' => 'Empresa X', 'document' => self::CNPJ]);
        CrmLeadItem::create(['tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'crm_produto_id' => $produto->id, 'quantidade' => 2]);
        app(CrmFunilService::class)->moverLead($lead, $this->etapa($tenant, 'convertido'), $admin);

        $this->assertNotNull($lead->fresh()->conta);
        $this->assertSame($antes, Client::withoutGlobalScopes()->count());
        Livewire::test(CrmContaResource\Pages\ListCrmContas::class)->assertSee('Empresa X');
        $this->assertSame(['conta', 'lead'], collect(CrmCampoCustom::entidadesDisponiveis())->keys()->sort()->values()->all(), 'entidade Clientes do ERP não é oferecida sem o módulo');
    }

    public function test_migration_is_reversible(): void
    {
        $migration = require base_path('database/migrations/2026_11_17_100000_create_crm_contas_produtos_e_itens.php');
        // A 6B (visitas) depende de crm_contas: sai antes e volta depois.
        $campo = require base_path('database/migrations/2026_11_18_100000_create_crm_veiculos_visitas_despesas.php');

        // A 6D-2 (propostas) depende de contas e do catálogo: também sai antes e volta depois.
        $propostas = require base_path('database/migrations/2026_11_21_100000_create_crm_propostas.php');

        $propostas->down();
        $campo->down();
        $migration->down();
        foreach (['crm_contas', 'crm_conta_contatos', 'crm_produtos', 'crm_lead_itens'] as $tabela) {
            $this->assertFalse(Schema::hasTable($tabela));
        }
        $this->assertFalse(Schema::hasColumn('crm_leads', 'crm_conta_id'));

        $migration->up();
        $campo->up();
        $propostas->up();
        $this->assertTrue(Schema::hasTable('crm_contas'));
        $this->assertTrue(Schema::hasColumn('whatsapp_conversas', 'crm_conta_id'));
        $this->assertSame(0, DB::table('crm_contas')->count());
    }
}
