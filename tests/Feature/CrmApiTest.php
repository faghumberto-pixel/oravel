<?php

namespace Tests\Feature;

use App\Filament\Pages\TokensApiCrm;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CrmEtapa;
use App\Models\CrmLead;
use App\Models\CrmLeadContact;
use App\Models\CrmLeadInteraction;
use App\Models\CrmPipeline;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappConversa;
use App\Services\CrmFunilPadrao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Fase 5 do CRM: API /api/crm/v1 por token. */
class CrmApiTest extends TestCase
{
    use DatabaseTransactions;

    private const CNPJ = '11.222.333/0001-81';

    private const BASE = '/api/crm/v1';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('x');
        Cache::flush();
        Http::fake(['brasilapi.com.br/*' => Http::response([
            'razao_social' => 'ACME LTDA', 'nome_fantasia' => 'ACME', 'descricao_tipo_de_logradouro' => 'RUA', 'logradouro' => 'DAS FLORES',
            'numero' => '10', 'bairro' => 'CENTRO', 'municipio' => 'CAMPINAS', 'uf' => 'SP', 'cep' => '13010000',
            'ddd_telefone_1' => '1933334444', 'email' => 'contato@acme.com.br',
        ], 200), '*' => Http::response([], 404)]);
    }

    private function tenant(?array $features = null): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano API '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => $features ?? ['tabela_crm_leads' => true, 'tabela_clients' => true, 'tabela_proposta_comercial' => true],
        ]);

        return Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function admin(Tenant $t): User
    {
        $u = User::create(['name' => 'Admin '.uniqid(), 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    /** Vendedor sem "ver todos os leads", com permissões de ler/criar/editar lead. */
    private function vendedor(Tenant $t): User
    {
        $u = User::create(['name' => 'Vendedor '.uniqid(), 'email' => 'v-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'comercial', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        foreach (['ler_crm_lead', 'criar_crm_lead', 'editar_crm_lead', 'ler_cliente', 'ler_proposta_comercial'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $u->givePermissionTo($p);
        }

        return $u;
    }

    private function api(User $u, array $abilities = ['crm:read', 'crm:write'], ?\DateTimeInterface $expira = null): self
    {
        // Só em teste: depois de uma requisição o guard padrão fica 'sanctum' e guarda o usuário anterior.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $token = $u->createToken('teste', $abilities, $expira ?? now()->addDays(30))->plainTextToken;

        return $this->withToken($token);
    }

    private function etapa(Tenant $t, string $slug): CrmEtapa
    {
        CrmFunilPadrao::garantir($t->id);

        return CrmEtapa::withoutGlobalScopes()->where('tenant_id', $t->id)->where('slug', $slug)->firstOrFail();
    }

    // ---------------------------------------------------------------- autenticação e conta

    public function test_missing_invalid_expired_and_revoked_tokens_get_401_in_the_error_format(): void
    {
        $t = $this->tenant();
        $u = $this->admin($t);

        $this->getJson(self::BASE.'/eu')->assertStatus(401)->assertJsonPath('erro.codigo', 'nao_autenticado');
        $this->withToken('lixo')->getJson(self::BASE.'/eu')->assertStatus(401);

        $this->api($u, expira: now()->subMinute())->getJson(self::BASE.'/eu')->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $novo = $u->createToken('x', ['crm:read'], now()->addDay());
        $novo->accessToken->delete();
        $this->withToken($novo->plainTextToken)->getJson(self::BASE.'/eu')->assertStatus(401);
    }

    public function test_token_needs_the_route_ability(): void
    {
        $u = $this->admin($this->tenant());

        $this->api($u, ['crm:read'])->postJson(self::BASE.'/leads', ['nome' => 'X'])
            ->assertStatus(403)->assertJsonPath('erro.codigo', 'sem_habilidade');
        $this->api($u, ['crm:write'])->getJson(self::BASE.'/eu')->assertStatus(403);
        $this->api($u, ['crm:read'])->getJson(self::BASE.'/eu')->assertOk();
    }

    public function test_unapproved_blocked_and_module_off_accounts_are_refused(): void
    {
        $t = $this->tenant();
        $pendente = $this->admin($t);
        $pendente->forceFill(['is_approved' => false])->save();
        $this->api($pendente)->getJson(self::BASE.'/eu')->assertStatus(403)->assertJsonPath('erro.codigo', 'usuario_nao_aprovado');

        $bloqueado = $this->tenant();
        $bloqueado->forceFill(['asaas_payment_status' => Tenant::PAYMENT_STATUS_CANCELADO])->save();
        $this->api($this->admin($bloqueado))->getJson(self::BASE.'/eu')->assertStatus(403)->assertJsonPath('erro.codigo', 'conta_bloqueada');

        $semCrm = $this->tenant(['tabela_clients' => true]);
        $this->api($this->admin($semCrm))->getJson(self::BASE.'/eu')->assertStatus(403)->assertJsonPath('erro.codigo', 'modulo_desligado');
    }

    public function test_eu_and_configuracao_describe_the_user_and_the_tenant_lists(): void
    {
        $t = $this->tenant();
        $u = $this->admin($t);
        $outro = $this->tenant();
        CrmFunilPadrao::garantir($outro->id);

        $this->api($u)->getJson(self::BASE.'/eu')->assertOk()
            ->assertJsonPath('data.usuario.id', $u->id)
            ->assertJsonPath('data.empresa.id', $t->id)
            ->assertJsonPath('data.ve_todos_os_leads', true)
            ->assertJsonPath('data.permissoes.criar_lead', true);

        $r = $this->api($u)->getJson(self::BASE.'/configuracao')->assertOk();
        $r->assertJsonPath('data.funis.0.nome', 'Funil de Vendas')->assertJsonCount(5, 'data.funis.0.etapas');
        $this->assertCount(7, $r->json('data.motivos_perda'));
        $this->assertSame([$u->id], collect($r->json('data.responsaveis'))->pluck('id')->all(), 'só usuários do próprio tenant');
    }

    // ---------------------------------------------------------------- busca por telefone

    public function test_phone_search_matches_formats_and_sources_and_respects_visibility_and_tenant(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $vendedor = $this->vendedor($t);
        $outro = $this->tenant();

        $meu = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Meu Lead', 'phone' => '(19) 99999-0000', 'assigned_user_id' => $vendedor->id]);
        $alheio = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Lead do Admin', 'whatsapp' => '+55 19 98888-1111', 'assigned_user_id' => $admin->id]);
        $viaContato = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Lead por contato', 'assigned_user_id' => $vendedor->id]);
        CrmLeadContact::create(['tenant_id' => $t->id, 'crm_lead_id' => $viaContato->id, 'name' => 'Fulano', 'phone' => '19 97777-2222']);
        $cliente = Client::create(['tenant_id' => $t->id, 'name' => 'Cliente S/A', 'phone' => '1933334444', 'whatsapp' => '19966665555']);
        $viaConversa = Client::create(['tenant_id' => $t->id, 'name' => 'Cliente da Conversa']);
        DB::table('whatsapp_numeros')->exists(); // tabela existe; conversa sem número é permitida abaixo
        CrmLead::create(['tenant_id' => $outro->id, 'name' => 'Lead de outro tenant', 'phone' => '19999990000']);

        $buscar = fn (User $u, string $tel) => collect($this->api($u)->getJson(self::BASE.'/contatos/buscar?telefone='.urlencode($tel))->assertOk()->json('data'));

        $this->assertSame(['Meu Lead'], $buscar($vendedor, '+5519999990000')->pluck('nome')->all(), 'com +55 e DDD');
        $this->assertSame(['Meu Lead'], $buscar($vendedor, '99999-0000')->pluck('nome')->all(), 'só os 8 últimos');
        $this->assertSame([], $buscar($vendedor, '19988881111')->all(), 'vendedor não vê lead do admin');
        $this->assertSame(['Lead do Admin'], $buscar($admin, '5519988881111')->pluck('nome')->all());
        $this->assertSame(['Lead por contato'], $buscar($vendedor, '19977772222')->pluck('nome')->all(), 'telefone do contato do lead');
        $this->assertSame('cliente', $buscar($vendedor, '19966665555')->first()['tipo']);
        $this->assertSame([], $buscar($vendedor, '123')->all(), 'curto demais');
        $this->assertSame(1, $buscar($admin, '19999990000')->count(), 'o lead do outro tenant nunca aparece');
        $this->assertNotNull($viaConversa);
    }

    // ---------------------------------------------------------------- CNPJ

    public function test_cnpj_endpoint_validates_maps_and_warns_about_duplicates(): void
    {
        $t = $this->tenant();
        $u = $this->admin($t);
        Client::create(['tenant_id' => $t->id, 'name' => 'Já cliente', 'document' => self::CNPJ]);

        $this->api($u)->getJson(self::BASE.'/cnpj/11222333000182')->assertStatus(422)->assertJsonPath('erro.codigo', 'cnpj_invalido');

        $r = $this->api($u)->getJson(self::BASE.'/cnpj/11222333000181')->assertOk();
        $r->assertJsonPath('data.dados.company_name', 'ACME LTDA')->assertJsonPath('data.dados.uf', 'SP');
        $this->assertStringContainsString('Já cliente', $r->json('data.duplicidade'));
    }

    // ---------------------------------------------------------------- criar lead

    public function test_create_lead_with_defaults_value_deadline_follow_up_and_custom_data(): void
    {
        $t = $this->tenant();
        $u = $this->admin($t);

        $r = $this->api($u)->postJson(self::BASE.'/leads', [
            'nome' => 'Maria', 'empresa' => 'Acme', 'telefone' => '19999990000', 'valor' => 15000, 'origem' => 'Site',
            'previsao_fechamento' => '2026-12-20', 'proximo_contato' => now()->addDays(3)->toDateString(), 'observacao' => 'Quer 2 geradores',
        ])->assertStatus(201);

        $r->assertJsonPath('data.etapa.nome', 'Novo')->assertJsonPath('data.valor', 15000)->assertJsonPath('data.previsao_fechamento', '2026-12-20')
            ->assertJsonPath('data.responsavel.id', $u->id);
        $lead = CrmLead::findOrFail($r->json('data.id'));
        $this->assertSame('site', $lead->source);
        $this->assertSame(now()->addDays(3)->toDateString(), $lead->next_followup_date->toDateString(), 'follow-up da interação refletido no lead');
        $this->assertSame(1, $lead->interactions()->count());
        $this->assertSame($u->id, $lead->interactions->first()->user_id);
    }

    public function test_create_lead_validates_stage_funnel_and_responsible_per_tenant(): void
    {
        $t = $this->tenant();
        $u = $this->admin($t);
        $outro = $this->tenant();
        $funilOutro = CrmFunilPadrao::garantir($outro->id);
        $etapaOutro = $this->etapa($outro, 'novo');
        $intruso = $this->admin($outro);
        $perdido = $this->etapa($t, 'perdido');

        $this->api($u)->postJson(self::BASE.'/leads', ['nome' => 'A', 'funil_id' => $funilOutro])->assertStatus(422)->assertJsonPath('erro.campos.funil_id.0', 'Funil não encontrado.');
        $this->api($u)->postJson(self::BASE.'/leads', ['nome' => 'A', 'etapa_id' => $etapaOutro->id])->assertStatus(422);
        $this->api($u)->postJson(self::BASE.'/leads', ['nome' => 'A', 'etapa_id' => $perdido->id])->assertStatus(422);
        $this->api($u)->postJson(self::BASE.'/leads', ['nome' => 'A', 'responsavel_id' => $intruso->id])->assertStatus(422);
        $this->api($u)->postJson(self::BASE.'/leads', ['telefone' => '1'])->assertStatus(422)->assertJsonPath('erro.codigo', 'dados_invalidos');
        $this->assertSame(0, CrmLead::count());

        $qualificado = $this->etapa($t, 'qualificado');
        $this->api($u)->postJson(self::BASE.'/leads', ['nome' => 'Ok', 'etapa_id' => $qualificado->id])->assertStatus(201)->assertJsonPath('data.etapa.nome', 'Qualificado');
    }

    public function test_salesperson_without_see_all_always_owns_the_lead_they_create(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $v = $this->vendedor($t);

        $r = $this->api($v)->postJson(self::BASE.'/leads', ['nome' => 'Do vendedor', 'responsavel_id' => $admin->id])->assertStatus(201);

        $r->assertJsonPath('data.responsavel.id', $v->id);
        $this->api($v)->getJson(self::BASE.'/leads/'.$r->json('data.id'))->assertOk();
    }

    public function test_duplicates_by_document_phone_and_existing_client_return_409_unless_forced(): void
    {
        $t = $this->tenant();
        $u = $this->admin($t);
        $outroVendedor = $this->vendedor($t);
        $existente = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Existente', 'document' => self::CNPJ, 'phone' => '(19) 99999-0000', 'assigned_user_id' => $u->id]);
        Client::create(['tenant_id' => $t->id, 'name' => 'Cliente', 'document' => '45.723.174/0001-10']);

        $r = $this->api($u)->postJson(self::BASE.'/leads', ['nome' => 'Dup', 'documento' => '11222333000181'])->assertStatus(409)->assertJsonPath('erro.codigo', 'duplicado');
        $r->assertJsonPath('erro.existente.id', $existente->id);

        $this->api($u)->postJson(self::BASE.'/leads', ['nome' => 'Dup tel', 'telefone' => '+55 19 99999-0000'])->assertStatus(409);
        $this->api($u)->postJson(self::BASE.'/leads', ['nome' => 'Cli', 'documento' => '45723174000110'])->assertStatus(409)->assertJsonPath('erro.codigo', 'ja_e_cliente');

        // Vendedor sabe que existe, mas não recebe os dados do lead do colega.
        $this->api($outroVendedor)->postJson(self::BASE.'/leads', ['nome' => 'Dup', 'documento' => '11222333000181'])
            ->assertStatus(409)->assertJsonPath('erro.existente', null);

        $this->api($u)->postJson(self::BASE.'/leads', ['nome' => 'Forçado', 'documento' => '11222333000181', 'forcar' => true])->assertStatus(201);
        $this->assertSame(2, CrmLead::where('document', self::CNPJ)->orWhere('document', '11222333000181')->count());
    }

    public function test_cnpj_autofill_only_fills_blank_fields(): void
    {
        $t = $this->tenant();
        $u = $this->admin($t);

        $r = $this->api($u)->postJson(self::BASE.'/leads', ['nome' => 'Fulano', 'documento' => self::CNPJ, 'empresa' => 'Nome que eu digitei', 'preencher_cnpj' => true])->assertStatus(201);

        $lead = CrmLead::findOrFail($r->json('data.id'));
        $this->assertSame('Nome que eu digitei', $lead->company_name);
        $this->assertSame('CAMPINAS', $lead->city);
        $this->assertSame('SP', $lead->uf);
    }

    // ---------------------------------------------------------------- mover etapa e interações

    public function test_move_stage_requires_reason_and_respects_ownership_and_tenant(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $v = $this->vendedor($t);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'L', 'assigned_user_id' => $v->id]);
        $alheio = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Alheio', 'assigned_user_id' => $admin->id]);
        $perdido = $this->etapa($t, 'perdido');
        $outroTenantEtapa = $this->etapa($this->tenant(), 'qualificado');

        $this->api($v)->patchJson(self::BASE."/leads/{$lead->id}/etapa", ['etapa_id' => $perdido->id])->assertStatus(422)->assertJsonPath('erro.codigo', 'dados_invalidos');
        $this->api($v)->patchJson(self::BASE."/leads/{$lead->id}/etapa", ['etapa_id' => $perdido->id, 'motivo' => 'concorrencia'])->assertStatus(422)->assertJsonPath('erro.campos.detalhe.0', 'Descreva o detalhe do motivo da perda.');
        $this->api($v)->patchJson(self::BASE."/leads/{$lead->id}/etapa", ['etapa_id' => $perdido->id, 'motivo' => 'preco'])->assertOk()->assertJsonPath('data.etapa.tipo', 'perdido');
        $this->api($v)->patchJson(self::BASE."/leads/{$lead->id}/etapa", ['etapa_id' => $outroTenantEtapa->id])->assertStatus(422);
        $this->api($v)->patchJson(self::BASE."/leads/{$alheio->id}/etapa", ['etapa_id' => $this->etapa($t, 'qualificado')->id])->assertStatus(404);
    }

    public function test_register_text_and_audio_interactions_with_private_storage(): void
    {
        Storage::fake('media_private');
        $t = $this->tenant();
        $u = $this->admin($t);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'L']);
        $outro = CrmLead::withoutGlobalScopes()->create(['tenant_id' => $this->tenant()->id, 'name' => 'De outro tenant']);

        $this->api($u)->postJson(self::BASE."/leads/{$lead->id}/interacoes", ['resumo' => 'Falei com ele', 'direcao' => 'saida', 'proximo_contato' => now()->addDay()->toDateString()])
            ->assertStatus(201)->assertJsonPath('data.canal', 'whatsapp')->assertJsonPath('data.direcao', 'saida')->assertJsonPath('data.tem_audio', false);

        $audio = UploadedFile::fake()->createWithContent('voz.wav', 'RIFF'.pack('V', 36).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8).'data'.pack('V', 0))->mimeType('audio/wav');
        $r = $this->api($u)->post(self::BASE."/leads/{$lead->id}/interacoes", ['transcricao' => 'texto do áudio', 'audio' => $audio], ['Accept' => 'application/json'])
            ->assertStatus(201)->assertJsonPath('data.tem_audio', true);

        $interacao = CrmLeadInteraction::findOrFail($r->json('data.id'));
        $this->assertSame('media_private', $interacao->getFirstMedia('audio')->disk);
        $this->assertSame(2, $lead->interactions()->count());

        // Arquivo que se diz áudio mas é texto: recusado, sem deixar interação pela metade.
        $falso = UploadedFile::fake()->createWithContent('falso.ogg', 'isto nao e audio')->mimeType('audio/ogg');
        $antes = $lead->interactions()->count();
        $this->api($u)->post(self::BASE."/leads/{$lead->id}/interacoes", ['resumo' => 'x', 'audio' => $falso], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('erro.campos.audio.0', 'O arquivo enviado não é um áudio aceito.');
        $this->assertSame($antes, $lead->interactions()->count());

        $this->api($u)->postJson(self::BASE."/leads/{$lead->id}/interacoes", [])->assertStatus(422);
        $pdf = UploadedFile::fake()->createWithContent('x.pdf', '%PDF')->mimeType('application/pdf');
        $this->api($u)->post(self::BASE."/leads/{$lead->id}/interacoes", ['resumo' => 'x', 'audio' => $pdf], ['Accept' => 'application/json'])->assertStatus(422);
        $this->api($u)->postJson(self::BASE."/leads/{$outro->id}/interacoes", ['resumo' => 'x'])->assertStatus(404);
    }

    // ---------------------------------------------------------------- oportunidades

    public function test_opportunities_list_only_open_leads_and_in_progress_proposals(): void
    {
        $t = $this->tenant();
        $u = $this->admin($t);
        $cliente = Client::create(['tenant_id' => $t->id, 'name' => 'Cliente']);
        $aberto = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Aberto', 'client_id' => $cliente->id, 'stage' => 'qualificado']);
        CrmLead::create(['tenant_id' => $t->id, 'name' => 'Ganho', 'client_id' => $cliente->id, 'stage' => 'convertido']);
        $mk = fn ($status, $extra = []) => PropostaComercial::create(['tenant_id' => $t->id, 'client_id' => $cliente->id, 'seller_user_id' => $u->id, 'status' => $status] + $extra);
        $mk(PropostaComercial::STATUS_APROVADA_INTERNA);
        $mk(PropostaComercial::STATUS_RASCUNHO);
        $mk(PropostaComercial::STATUS_ACEITA_PELO_CLIENTE);
        $mk(PropostaComercial::STATUS_RECUSADA_PELO_CLIENTE);

        $r = $this->api($u)->getJson(self::BASE."/contatos/cliente/{$cliente->id}/oportunidades")->assertOk();
        $this->assertSame([$aberto->id], collect($r->json('data.leads_abertos'))->pluck('id')->all());
        $this->assertCount(2, $r->json('data.propostas_em_andamento'));

        $this->api($u)->getJson(self::BASE.'/contatos/fornecedor/x/oportunidades')->assertStatus(422);
        $this->api($u)->getJson(self::BASE.'/contatos/cliente/'.Str::uuid().'/oportunidades')->assertStatus(404);
        $this->api($this->admin($this->tenant()))->getJson(self::BASE."/contatos/cliente/{$cliente->id}/oportunidades")->assertStatus(404);
    }

    public function test_lead_sheet_never_leaks_across_tenants(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $leadB = CrmLead::withoutGlobalScopes()->create(['tenant_id' => $b->id, 'name' => 'Segredo B']);

        $this->api($this->admin($a))->getJson(self::BASE."/leads/{$leadB->id}")->assertStatus(404);
        $this->api($this->admin($b))->getJson(self::BASE."/leads/{$leadB->id}")->assertOk()->assertJsonMissingPath('data.tenant_id');
    }

    public function test_write_rate_limit_is_30_per_minute_per_token(): void
    {
        $t = $this->tenant();
        $u = $this->admin($t);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'L']);
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $token = $u->createToken('limite', ['crm:read', 'crm:write'], now()->addDay())->plainTextToken;

        for ($i = 0; $i < 30; $i++) {
            $this->withToken($token)->postJson(self::BASE."/leads/{$lead->id}/interacoes", ['resumo' => "n$i"])->assertStatus(201);
            $this->app['auth']->forgetGuards();
            $this->app['auth']->shouldUse('web');
        }

        $this->withToken($token)->postJson(self::BASE."/leads/{$lead->id}/interacoes", ['resumo' => 'extra'])->assertStatus(429)->assertJsonPath('erro.codigo', 'muitas_requisicoes');
    }

    // ---------------------------------------------------------------- página de tokens e migration

    public function test_tokens_page_creates_shows_once_lists_own_and_revokes(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $v = $this->vendedor($t);
        $v->createToken('Do vendedor', ['crm:read'], now()->addDay());
        $this->actingAs($admin);

        $page = Livewire::test(TokensApiCrm::class)->set('nome', 'Token Admin ZZ')->set('validadeDias', 30)->call('criar');
        $texto = $page->get('tokenNovo');
        $this->assertNotEmpty($texto);
        $page->assertSee('Token Admin ZZ')->assertSee('Do vendedor')->call('fecharToken')->assertSet('tokenNovo', null);

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->withToken($texto)->getJson(self::BASE.'/eu')->assertOk();

        $id = DB::table('personal_access_tokens')->where('name', 'Token Admin ZZ')->value('id');
        $this->actingAs($admin);
        Livewire::test(TokensApiCrm::class)->call('revogar', $id);
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->withToken($texto)->getJson(self::BASE.'/eu')->assertStatus(401);

        // Vendedor só vê os próprios.
        $this->actingAs($v);
        Livewire::test(TokensApiCrm::class)->assertSee('Do vendedor')->assertDontSee('Token Admin ZZ');
    }

    public function test_migration_is_reversible_and_tokens_use_uuid_owner(): void
    {
        $migration = require base_path('database/migrations/2026_11_16_100000_crm_api_tokens_uuid_previsao_e_interacao_anexos.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('crm_leads', 'previsao_fechamento'));
        $this->assertFalse(Schema::hasColumn('crm_lead_interactions', 'direcao'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('crm_leads', 'previsao_fechamento'));
        $this->assertTrue(Schema::hasColumn('crm_lead_interactions', 'transcricao'));
        $u = $this->admin($this->tenant());
        $this->assertNotEmpty($u->createToken('uuid-ok', ['crm:read'])->plainTextToken, 'tokenable_id aceita uuid');
        $this->assertNotNull(ClientContact::class);
        $this->assertNotNull(WhatsappConversa::class);
        $this->assertNotNull(CrmPipeline::class);
    }
}
