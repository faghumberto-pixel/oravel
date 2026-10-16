<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportarPlanilhaCrm;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CrmCampoCustom;
use App\Models\CrmImportacao;
use App\Models\CrmLead;
use App\Models\CrmLeadContact;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CrmImport\CrmImportDestinos;
use App\Services\CrmImport\CrmImportLeitor;
use App\Services\CrmImport\CrmImportService;
use App\Support\CrmCamposCustom;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** Fase 4 do CRM: importador de planilha (leitura, mapeamento, dedup, simulação, página). */
class CrmImportTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ_A = '11.222.333/0001-81';

    private const CNPJ_B = '45.723.174/0001-10';

    protected function setUp(): void
    {
        parent::setUp();
        CrmCamposCustom::esquecer();
    }

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Imp '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_crm_leads' => true, 'tabela_clients' => true, 'tabela_crm_campos_custom' => true],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function arquivo(string $conteudo, string $ext = 'csv'): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'imp').'.'.$ext;
        file_put_contents($caminho, $conteudo);

        return $caminho;
    }

    /** Roda o serviço com o mapeamento sugerido pelos cabeçalhos. */
    private function importar(string $destino, string $csv, Tenant $tenant, array $opcoes = [], bool $simular = false, ?array $mapeamento = null)
    {
        $lido = app(CrmImportLeitor::class)->ler($this->arquivo($csv));
        $mapeamento ??= CrmImportDestinos::sugerirMapeamento($lido['cabecalhos'], $destino, $tenant->id);

        return app(CrmImportService::class)->processar($destino, $lido['linhas'], $mapeamento, $opcoes, $tenant, $simular);
    }

    // ------------------------------------------------------------ leitor

    public function test_reader_handles_semicolon_comma_bom_and_windows_1252(): void
    {
        $leitor = app(CrmImportLeitor::class);

        $a = $leitor->ler($this->arquivo("\xEF\xBB\xBFNome;Cidade\nAna;Campinas\nBeto;Jundiaí\n"));
        $this->assertSame(['Nome', 'Cidade'], $a['cabecalhos']);
        $this->assertSame('Jundiaí', $a['linhas'][3][1]);

        $b = $leitor->ler($this->arquivo("Nome,Cidade\n\"Silva, João\",Campinas\n"));
        $this->assertSame('Silva, João', $b['linhas'][2][0]);

        $latin = mb_convert_encoding("Nome;Cidade\nJosé;São Paulo\n", 'Windows-1252', 'UTF-8');
        $c = $leitor->ler($this->arquivo($latin));
        $this->assertSame('São Paulo', $c['linhas'][2][1]);
    }

    public function test_reader_reads_the_first_sheet_of_an_xlsx(): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $w = new Writer;
        $w->openToFile($caminho);
        $w->addRow(Row::fromValues(['Nome', 'Valor']));
        $w->addRow(Row::fromValues(['Ana', 1500]));
        $w->close();

        $lido = app(CrmImportLeitor::class)->ler($caminho);

        $this->assertSame(['Nome', 'Valor'], $lido['cabecalhos']);
        $this->assertSame(['Ana', '1500'], $lido['linhas'][2]);
    }

    public function test_reader_rejects_empty_header_only_and_oversized_files(): void
    {
        $leitor = app(CrmImportLeitor::class);

        foreach (['', "Nome;Cidade\n"] as $conteudo) {
            try {
                $leitor->ler($this->arquivo($conteudo));
                $this->fail('deveria recusar');
            } catch (\DomainException) {
                $this->assertTrue(true);
            }
        }

        $grande = "Nome\n".str_repeat("x\n", CrmImportLeitor::MAX_LINHAS + 1);
        $this->expectException(\DomainException::class);
        $leitor->ler($this->arquivo($grande));
    }

    public function test_mapping_suggestion_uses_synonyms_ignoring_accents(): void
    {
        $mapa = CrmImportDestinos::sugerirMapeamento(['Razão Social', 'CNPJ', 'Celular', 'E-mail', 'Estado'], 'cliente');

        $this->assertSame(0, $mapa['name']);
        $this->assertSame(1, $mapa['document']);
        $this->assertSame(2, $mapa['whatsapp']);
        $this->assertSame(3, $mapa['email']);
        $this->assertSame(4, $mapa['state']);
        $this->assertNull($mapa['fantasy_name']);
    }

    // ------------------------------------------------------------ leads

    public function test_leads_are_created_with_conversions_warnings_and_custom_fields(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $vendedor = User::create(['name' => 'Vend', 'email' => 'vend@x.com', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        CrmCampoCustom::create(['tenant_id' => $tenant->id, 'entidade' => 'lead', 'nome' => 'Frota', 'tipo' => 'numero']);
        CrmCampoCustom::create(['tenant_id' => $tenant->id, 'entidade' => 'lead', 'nome' => 'Visita', 'tipo' => 'data']);

        $csv = "Nome;Empresa;CNPJ;E-mail;UF;Valor;Origem;Etapa;Responsável;Frota (personalizado);Visita (personalizado)\n"
            .'Ana;Acme Ltda;'.self::CNPJ_A.";ana@acme.com;sp;R\$ 1.234,56;Site;Qualificado;vend@x.com;12;25/12/2026\n"
            .'Beto;Beta SA;;beto@beta.com;RJ;500;Origem Inexistente;Etapa Inexistente;ninguem@x.com;;'."\n";

        $r = $this->importar('lead', $csv, $tenant);

        $this->assertSame(2, $r->criados);
        $this->assertSame(0, $r->erros);
        $ana = CrmLead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('email', 'ana@acme.com')->firstOrFail();
        $this->assertEquals(1234.56, $ana->estimated_value);
        $this->assertSame('SP', $ana->uf);
        $this->assertSame('site', $ana->source);
        $this->assertSame('qualificado', $ana->stage);
        $this->assertSame($vendedor->id, $ana->assigned_user_id);
        $this->assertEquals(12, $ana->custom['frota']);
        $this->assertSame('2026-12-25', $ana->custom['visita']);

        $beto = CrmLead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('email', 'beto@beta.com')->firstOrFail();
        $this->assertSame('novo', $beto->stage, 'etapa desconhecida cai na 1ª aberta');
        $this->assertSame('outro', $beto->source);
        $this->assertNull($beto->assigned_user_id);
        $this->assertGreaterThanOrEqual(3, $r->avisos);
    }

    public function test_invalid_rows_only_fail_that_line(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $csv = "Nome;E-mail;CNPJ\nOk;ok@x.com;\nRuim;email-invalido;\nDoc Ruim;d@x.com;11.222.333/0001-82\n;;\n";

        $r = $this->importar('lead', $csv, $tenant);

        $this->assertSame(1, $r->criados);
        $this->assertSame(2, $r->erros);
        $this->assertSame(1, CrmLead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_dedup_by_document_email_inside_file_and_existing_client_and_other_tenants_dont_count(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        [$outro] = $this->makeTenantAdmin();
        CrmLead::create(['tenant_id' => $outro->id, 'name' => 'Lead do outro', 'document' => self::CNPJ_A]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Já existe', 'document' => self::CNPJ_B]);
        Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Velho', 'document' => '12.345.678/0001-95']);

        $csv = "Nome;CNPJ;E-mail\n"
            ."Novo A;11222333000181;a@x.com\n"            // mesmo CNPJ de outro tenant: cria
            .'Repetido;'.self::CNPJ_A.";rep@x.com\n"      // repetido no arquivo (mesmo CNPJ, com máscara)
            ."Já;45723174000110;j@x.com\n"                // já existe lead com o CNPJ (sem máscara)
            ."Cli;12.345.678/0001-95;c@x.com\n"           // já é cliente
            ."Sem doc;;sem@x.com\n"
            ."Sem doc 2;;SEM@x.com\n";                     // mesmo e-mail dentro do arquivo

        $r = $this->importar('lead', $csv, $tenant);

        $this->assertSame(2, $r->criados, 'Novo A e Sem doc');
        $this->assertSame(4, $r->ignorados);
        $textos = collect($r->linhas)->pluck('mensagem')->implode('|');
        $this->assertStringContainsString('primeira vez na linha 2', $textos);
        $this->assertStringContainsString('Já é cliente', $textos);
    }

    public function test_existing_modes_ignore_complete_and_overwrite_never_erasing_with_blanks(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Original', 'document' => self::CNPJ_A, 'city' => 'Campinas', 'phone' => null]);
        $csv = "Nome;CNPJ;Cidade;Telefone\nNovo Nome;".self::CNPJ_A.";Jundiaí;1933334444\n";
        $buscar = fn () => CrmLead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame(1, $this->importar('lead', $csv, $tenant, ['se_existir' => 'ignorar'])->ignorados);
        $this->assertNull($buscar()->phone);

        $r = $this->importar('lead', $csv, $tenant, ['se_existir' => 'completar']);
        $this->assertSame(1, $r->atualizados);
        $this->assertSame('Campinas', $buscar()->city, 'completar não troca o que já tem');
        $this->assertSame('1933334444', $buscar()->phone);
        $this->assertSame('Original', $buscar()->name);

        $this->importar('lead', "Nome;CNPJ;Cidade;Telefone\nNovo Nome;".self::CNPJ_A.";Jundiaí;\n", $tenant, ['se_existir' => 'sobrescrever']);
        $this->assertSame('Jundiaí', $buscar()->city);
        $this->assertSame('Novo Nome', $buscar()->name);
        $this->assertSame('1933334444', $buscar()->phone, 'célula vazia não apaga');
    }

    public function test_simulation_writes_nothing_and_matches_real_totals(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Existe', 'email' => 'e@x.com']);
        $csv = "Nome;E-mail\nNovo 1;n1@x.com\nNovo 2;n2@x.com\nExiste;e@x.com\nRuim;invalido\n";
        $antes = CrmLead::withoutGlobalScopes()->count();

        $sim = $this->importar('lead', $csv, $tenant, simular: true);
        $this->assertSame($antes, CrmLead::withoutGlobalScopes()->count(), 'simulação não grava');
        $this->assertTrue($sim->simulacao);

        $real = $this->importar('lead', $csv, $tenant);
        $this->assertSame($sim->totais(), $real->totais());
        $this->assertSame($antes + 2, CrmLead::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------ clientes e contatos

    public function test_clients_created_updated_and_duplicates_respected(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $csv = "Razão Social;CNPJ;Cidade;UF;Nicho\nAcme Ltda;".self::CNPJ_A.";Campinas;SP;Construção Civil\nBeta SA;".self::CNPJ_B.";Jundiaí;SP;\n";

        $r = $this->importar('cliente', $csv, $tenant);
        $this->assertSame(2, $r->criados);
        $acme = Client::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Acme Ltda')->firstOrFail();
        $this->assertSame('construcao_civil', $acme->activity_type);

        $this->assertSame(2, $this->importar('cliente', $csv, $tenant)->ignorados, 'mesmo arquivo de novo: tudo ignorado');

        $r3 = $this->importar('cliente', "Razão Social;CNPJ;Cidade\nAcme Ltda;".self::CNPJ_A.";Valinhos\n", $tenant, ['se_existir' => 'sobrescrever']);
        $this->assertSame(1, $r3->atualizados);
        $this->assertSame('Valinhos', $acme->fresh()->city);
    }

    public function test_contacts_link_by_document_or_name_and_fail_without_parent(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $cliente = Client::create(['tenant_id' => $tenant->id, 'name' => 'Acme Ltda', 'document' => self::CNPJ_A]);
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Fulano', 'company_name' => 'Beta SA']);

        $csv = "Nome;Cargo;E-mail;Principal;CNPJ do cliente;Cliente\n"
            .'Maria;Compras;m@acme.com;sim;'.self::CNPJ_A.";\n"
            ."João;Obras;j@acme.com;;;Acme Ltda\n"
            ."Perdido;X;p@x.com;;;Empresa Inexistente\n"
            .'Maria 2;Compras;m@acme.com;;'.self::CNPJ_A.";\n";          // mesmo e-mail: ignorado

        $r = $this->importar('contato', $csv, $tenant, ['pai' => 'cliente']);

        $this->assertSame(2, $r->criados);
        $this->assertSame(1, $r->erros);
        $this->assertSame(1, $r->ignorados);
        $this->assertSame(['João', 'Maria'], ClientContact::where('client_id', $cliente->id)->orderBy('name')->pluck('name')->all());
        $this->assertTrue((bool) ClientContact::where('name', 'Maria')->value('is_primary'));

        $r2 = $this->importar('contato', "Nome;Telefone;Cliente\nSicrano;1;Beta SA\n", $tenant, ['pai' => 'lead']);
        $this->assertSame(1, $r2->criados);
        $this->assertSame(1, CrmLeadContact::where('crm_lead_id', $lead->id)->count());
    }

    public function test_contact_import_never_attaches_to_another_tenants_client(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        [$outro] = $this->makeTenantAdmin();
        Client::create(['tenant_id' => $outro->id, 'name' => 'Cliente Alheio', 'document' => self::CNPJ_B]);

        $r = $this->importar('contato', "Nome;CNPJ\nIntruso;".self::CNPJ_B."\n", $tenant);

        $this->assertSame(1, $r->erros);
        $this->assertSame(0, ClientContact::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------ página

    public function test_page_flow_read_map_simulate_then_import_and_history(): void
    {
        Storage::fake('local');
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        $csv = "Nome;E-mail\nAna;ana@x.com\nBeto;beto@x.com\n";

        $page = Livewire::test(ImportarPlanilhaCrm::class)
            ->set('destino', 'lead')
            ->set('planilha', UploadedFile::fake()->createWithContent('leads.csv', $csv))
            ->call('lerPlanilha')
            ->assertSet('passo', 2)
            ->assertSet('totalLinhas', 2);

        // Importar direto, sem simular, é barrado.
        $page->call('importar');
        $this->assertSame(0, CrmLead::count());

        $page->call('simular')->assertSet('passo', 3);
        $this->assertSame(0, CrmLead::count());

        // Mudar o mapeamento invalida a simulação.
        $page->set('mapeamento.email', null);
        $page->call('importar');
        $this->assertSame(0, CrmLead::count(), 'simulação invalidada');

        $page->call('simular')->call('importar')->assertSet('passo', 4);
        $this->assertSame(2, CrmLead::count());
        $this->assertSame(1, CrmImportacao::where('destino', 'lead')->where('criados', 2)->count());
        $this->assertSame($admin->id, CrmImportacao::first()->user_id);
    }

    public function test_page_requires_mandatory_mapping_and_rejects_bad_files(): void
    {
        Storage::fake('local');
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(ImportarPlanilhaCrm::class)
            ->set('planilha', UploadedFile::fake()->createWithContent('vazio.csv', ''))
            ->call('lerPlanilha')
            ->assertSet('passo', 1);

        Livewire::test(ImportarPlanilhaCrm::class)
            ->set('planilha', UploadedFile::fake()->createWithContent('ok.csv', "Coluna A;Coluna B\n1;2\n"))
            ->call('lerPlanilha')
            ->assertSet('passo', 2)
            ->call('simular')
            ->assertSet('passo', 2);
    }

    public function test_user_without_create_permission_cannot_import(): void
    {
        Storage::fake('local');
        [$tenant] = $this->makeTenantAdmin();
        $sem = User::create(['name' => 'Sem', 'email' => 'sem-'.uniqid().'@x.com', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $sem->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $this->actingAs($sem);

        $this->assertFalse(ImportarPlanilhaCrm::canAccess(), 'sem viewAny não abre a página');
        Livewire::test(ImportarPlanilhaCrm::class)->assertForbidden();
    }

    public function test_template_download_has_headers_and_example_row(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        CrmCampoCustom::create(['tenant_id' => $tenant->id, 'entidade' => 'lead', 'nome' => 'Frota', 'tipo' => 'numero']);

        $csv = CrmImportDestinos::modeloCsv('lead', $tenant->id);

        $this->assertStringContainsString('Nome do contato', $csv);
        $this->assertStringContainsString('Frota (personalizado)', $csv);
        $this->assertStringContainsString('Construtora Exemplo Ltda', $csv);
        $this->assertSame($tenant->id, Tenancy::current()?->id);
    }
}
