<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ClientResource\RelationManagers\ClientContactsRelationManager;
use App\Filament\Resources\CrmLeadResource;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CrmLead;
use App\Models\CrmLeadContact;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\CpfCnpj;
use App\Services\CnpjLookupService;
use App\Support\Cnpj;
use App\Support\CrmCnpjPreenchimento;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 3 do CRM: autopreenchimento por CNPJ e contatos do cliente. */
class CrmCnpjContatosTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '11.222.333/0001-81';

    private function brasilApi(): array
    {
        return [
            'razao_social' => 'ACME LOCACAO DE EQUIPAMENTOS LTDA', 'nome_fantasia' => 'ACME Locações',
            'natureza_juridica' => 'Sociedade Empresária Limitada', 'descricao_tipo_de_logradouro' => 'RUA',
            'logradouro' => 'DAS FLORES', 'numero' => '100', 'complemento' => 'SALA 2', 'bairro' => 'CENTRO',
            'municipio' => 'CAMPINAS', 'uf' => 'SP', 'cep' => '13010000', 'ddd_telefone_1' => '1933334444',
            'email' => 'contato@acme.com.br',
        ];
    }

    private function fakeApi(): void
    {
        Cache::flush();
        Http::fake(['brasilapi.com.br/*' => Http::response($this->brasilApi(), 200), '*' => Http::response([], 404)]);
    }

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Cnpj '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_crm_leads' => true, 'tabela_clients' => true],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_cnpj_helper_validates_and_formats(): void
    {
        $this->assertTrue(Cnpj::valido(self::CNPJ));
        $this->assertTrue(Cnpj::valido('11222333000181'));
        $this->assertFalse(Cnpj::valido('11.222.333/0001-82'));
        $this->assertFalse(Cnpj::valido('11111111111111'));
        $this->assertFalse(Cnpj::valido('123'));
        $this->assertSame('11.222.333/0001-81', Cnpj::formatar('11222333000181'));

        $regra = fn ($v) => Validator::make(['d' => $v], ['d' => [new CpfCnpj]])->passes();
        $this->assertTrue($regra(self::CNPJ));
        $this->assertFalse($regra('11.222.333/0001-82'));
    }

    public function test_lookup_skips_invalid_cnpj_without_calling_the_api_and_caches_success(): void
    {
        $this->fakeApi();
        $svc = app(CnpjLookupService::class);

        $this->assertNull($svc->lookup('11.222.333/0001-82'));
        Http::assertNothingSent();

        $primeira = $svc->lookup(self::CNPJ);
        $segunda = $svc->lookup('11222333000181');

        $this->assertSame('ACME LOCACAO DE EQUIPAMENTOS LTDA', $primeira['razao_social']);
        $this->assertSame($primeira, $segunda);
        Http::assertSentCount(1);
    }

    public function test_lookup_returns_null_when_the_api_fails_and_does_not_cache_the_failure(): void
    {
        Cache::flush();
        Http::fake(['brasilapi.com.br/*' => Http::sequence()->push([], 404)->push($this->brasilApi(), 200), '*' => Http::response([], 404)]);
        $svc = app(CnpjLookupService::class);

        $this->assertNull($svc->lookup(self::CNPJ));
        $this->assertNotNull($svc->lookup(self::CNPJ), 'falha não fica em cache');
    }

    public function test_mapping_and_fill_only_empty_fields(): void
    {
        $dados = app(CnpjLookupService::class);
        $this->fakeApi();
        $r = $dados->lookup(self::CNPJ);

        $lead = CrmCnpjPreenchimento::mapear('lead', $r);
        $this->assertSame('ACME LOCACAO DE EQUIPAMENTOS LTDA', $lead['company_name']);
        $this->assertSame('RUA DAS FLORES, 100', $lead['address']);
        $this->assertSame('13010-000', $lead['cep']);
        $this->assertSame('SP', $lead['uf']);

        $cliente = CrmCnpjPreenchimento::mapear('cliente', $r);
        $this->assertSame('ACME Locações', $cliente['fantasy_name']);
        $this->assertSame('SP', $cliente['state']);
        $this->assertSame('13010-000', $cliente['zip_code']);

        $form = ['company_name' => 'Digitado por mim', 'city' => ''];
        $preenchidos = CrmCnpjPreenchimento::preencher($lead, fn ($c) => $form[$c] ?? null, function ($c, $v) use (&$form) {
            $form[$c] = $v;
        });

        $this->assertSame('Digitado por mim', $form['company_name'], 'nunca sobrescreve');
        $this->assertSame('CAMPINAS', $form['city']);
        $this->assertNotContains('Empresa', $preenchidos);
        $this->assertContains('Cidade', $preenchidos);
    }

    public function test_lead_form_button_fills_empty_fields_keeps_typed_and_warns_about_duplicate(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->fakeApi();
        Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente já existente', 'document' => self::CNPJ]);
        $this->actingAs($admin);

        Livewire::test(CrmLeadResource\Pages\CreateCrmLead::class)
            ->fillForm(['name' => 'Fulano', 'document' => self::CNPJ, 'city' => 'Minha Cidade'])
            ->callFormComponentAction('document', 'buscar_cnpj')
            ->assertFormSet([
                'company_name' => 'ACME LOCACAO DE EQUIPAMENTOS LTDA',
                'city' => 'Minha Cidade',
                'uf' => 'SP',
                'phone' => '1933334444',
            ])
            ->assertNotified('Atenção: possível duplicidade');
    }

    public function test_button_does_not_query_for_cpf_or_invalid_cnpj(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->fakeApi();
        $this->actingAs($admin);

        Livewire::test(CrmLeadResource\Pages\CreateCrmLead::class)
            ->fillForm(['name' => 'F', 'document' => '123.456.789-09'])
            ->callFormComponentAction('document', 'buscar_cnpj')
            ->assertNotified('A busca automática é só para CNPJ');

        Livewire::test(CrmLeadResource\Pages\CreateCrmLead::class)
            ->fillForm(['name' => 'F', 'document' => '11.222.333/0001-82'])
            ->callFormComponentAction('document', 'buscar_cnpj')
            ->assertNotified('CNPJ inválido');

        Http::assertNothingSent();
    }

    public function test_client_form_button_fills_fantasy_name_but_keeps_existing_name(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->fakeApi();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Nome já cadastrado']);
        $this->actingAs($admin);

        Livewire::test(ClientResource\Pages\EditClient::class, ['record' => $client->getKey()])
            ->fillForm(['document' => self::CNPJ])
            ->callFormComponentAction('document', 'buscar_cnpj')
            ->assertFormSet(['name' => 'Nome já cadastrado', 'fantasy_name' => 'ACME Locações', 'state' => 'SP', 'zip_code' => '13010-000']);
    }

    public function test_client_contacts_crud_single_primary_and_tenant_isolation(): void
    {
        [$a, $adminA] = $this->makeTenantAdmin();
        [$b] = $this->makeTenantAdmin();
        $clienteA = Client::create(['tenant_id' => $a->id, 'name' => 'Cliente A']);
        $clienteB = Client::create(['tenant_id' => $b->id, 'name' => 'Cliente B']);
        ClientContact::create(['tenant_id' => $b->id, 'client_id' => $clienteB->id, 'name' => 'Contato Secreto B']);
        $this->actingAs($adminA);

        $rm = fn () => Livewire::test(ClientContactsRelationManager::class, [
            'ownerRecord' => $clienteA, 'pageClass' => ClientResource\Pages\EditClient::class,
        ]);

        $rm()->callTableAction('create', data: ['name' => 'Maria', 'role_title' => 'Compras', 'is_primary' => true])->assertHasNoTableActionErrors();
        $rm()->callTableAction('create', data: ['name' => 'João', 'is_primary' => true])->assertHasNoTableActionErrors();

        $this->assertSame(['João'], ClientContact::where('client_id', $clienteA->id)->where('is_primary', true)->pluck('name')->all(), 'só um principal');
        $rm()->assertSee('Maria')->assertSee('João')->assertDontSee('Contato Secreto B');
        $this->assertSame(0, ClientContact::where('name', 'Contato Secreto B')->count(), 'escopo de tenant');
    }

    public function test_convert_to_client_copies_every_lead_contact(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead', 'company_name' => 'Empresa X']);
        CrmLeadContact::create(['tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'name' => 'Principal', 'phone' => '1', 'is_primary' => true]);
        CrmLeadContact::create(['tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'name' => 'Secundário', 'email' => 'a@b.com']);

        $client = $lead->convertToClient();

        $this->assertSame(['Principal', 'Secundário'], $client->contacts->pluck('name')->all());
        $this->assertSame('Principal', $client->contact_name, 'compatibilidade com contact_name');
        $this->assertSame(1, $client->contacts->where('is_primary', true)->count());
    }

    public function test_migration_backfills_primary_contact_from_contact_name_and_is_reversible(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $com = Client::create(['tenant_id' => $tenant->id, 'name' => 'Com contato', 'contact_name' => 'Fulana da Silva']);
        $sem = Client::create(['tenant_id' => $tenant->id, 'name' => 'Sem contato']);
        $migration = require base_path('database/migrations/2026_11_14_100000_create_client_contacts_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('client_contacts'));
        $this->assertSame('Fulana da Silva', DB::table('clients')->where('id', $com->id)->value('contact_name'));

        $migration->up();
        $this->assertSame(1, DB::table('client_contacts')->where('client_id', $com->id)->where('name', 'Fulana da Silva')->where('is_primary', true)->count());
        $this->assertSame(0, DB::table('client_contacts')->where('client_id', $sem->id)->count());
    }
}
