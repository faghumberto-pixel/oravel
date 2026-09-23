<?php

namespace Tests\Feature;

use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POST /api/inbound/leads: lead de formulário externo -> CrmLead do tenant dono do canal + aviso no
 * sino, sem depender de SMTP. O tenant vem SEMPRE do token do canal.
 *
 * DatabaseTransactions (e NÃO RefreshDatabase) de propósito: config/database.php fixa
 * 'default' => 'pgsql', então o DB_CONNECTION=sqlite do phpunit.xml não vale e qualquer
 * migrate:fresh rodaria contra o banco real do ambiente. Aqui tudo é desfeito por rollback.
 */
class InboundLeadTest extends TestCase
{
    use DatabaseTransactions;

    private const TOKEN = 'token-de-teste-canal-a';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant('oravel-'.uniqid());
        config([
            'cache.default' => 'array',
            'services.inbound.channels' => [
                'site-oravel' => ['token' => self::TOKEN, 'tenant_slug' => $this->tenant->slug],
            ],
        ]);
    }

    private function makeTenant(string $slug): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Inbound '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_crm_leads'],
        ]);

        return Tenant::create(['name' => 'Tenant '.$slug, 'slug' => $slug, 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function makeUser(Tenant $tenant, string $name, bool $admin = false, bool $approved = true): User
    {
        $user = User::create([
            'name' => $name, 'email' => strtolower($name).'-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => $approved,
        ]);

        if ($admin) {
            // new+save (e não Role::create): o create() do Spatie ignora tenant_id na checagem de duplicado,
            // mas o banco permite um 'admin' por tenant (índice único name+guard_name+tenant_id).
            $role = new Role(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
            $role->save();
            DB::table('model_has_roles')->insert([
                'role_id' => $role->id, 'model_type' => User::class, 'model_id' => $user->id,
            ]);
        }

        return $user;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'lead_id' => bin2hex(random_bytes(6)),
            'origem' => 'manutencao-industrial',
            'origem_label' => 'Manutenção Industrial',
            'name' => 'Fulano de Tal',
            'company' => 'Empresa A',
            'email' => 'fulano@empresa-a.com.br',
            'phone' => '(19) 99999-0000',
            'segmento' => 'Manutenção Industrial',
            'porte' => '6 a 15',
            'porte_label' => 'Nº de técnicos',
        ], $override);
    }

    private function send(array $payload, ?string $token = self::TOKEN)
    {
        return $this->postJson('/api/inbound/leads', $payload, $token === null ? [] : ['X-Channel-Token' => $token]);
    }

    private function leadsOf(Tenant $tenant)
    {
        return CrmLead::withoutGlobalScopes()->where('tenant_id', $tenant->id);
    }

    public function test_canal_desligado_ou_sem_token_devolve_401_e_nao_grava(): void
    {
        $this->makeUser($this->tenant, 'Admin', admin: true);

        config(['services.inbound.channels.site-oravel.token' => null]);
        $this->send($this->payload())->assertStatus(401);

        config(['services.inbound.channels.site-oravel.token' => self::TOKEN]);
        $this->send($this->payload(), token: null)->assertStatus(401);
        $this->send($this->payload(), token: 'errado')->assertStatus(401);
        $this->send($this->payload(), token: '')->assertStatus(401);

        $this->assertSame(0, $this->leadsOf($this->tenant)->count());
    }

    public function test_payload_invalido_devolve_422_e_nao_grava(): void
    {
        $this->makeUser($this->tenant, 'Admin', admin: true);

        $this->send($this->payload(['origem' => 'Origem Inválida!']))->assertStatus(422);
        $this->send($this->payload(['porte' => str_repeat('x', 31)]))->assertStatus(422);
        $this->send($this->payload(['email' => 'nao-e-email']))->assertStatus(422);
        $this->send($this->payload(['phone' => 'abc']))->assertStatus(422);
        $this->send(['origem' => 'manutencao-industrial'])->assertStatus(422);

        $this->assertSame(0, $this->leadsOf($this->tenant)->count());
    }

    public function test_lead_valido_cria_crm_lead_interacao_e_notifica_os_admins(): void
    {
        $admin = $this->makeUser($this->tenant, 'Admin', admin: true);
        $comum = $this->makeUser($this->tenant, 'Comum');

        $this->send($this->payload())->assertStatus(201)->assertJsonPath('success', true);

        $lead = $this->leadsOf($this->tenant)->firstOrFail();
        $this->assertSame('Fulano de Tal', $lead->name);
        $this->assertSame('Empresa A', $lead->company_name);
        $this->assertSame('fulano@empresa-a.com.br', $lead->email);
        $this->assertSame('site_manutencao-industrial', $lead->source);
        $this->assertSame(CrmLead::STAGE_NOVO, $lead->stage);
        $this->assertSame('Manutenção Industrial', $lead->segment);
        $this->assertSame('6 a 15', $lead->company_size);

        $interacao = CrmLeadInteraction::withoutGlobalScopes()->where('crm_lead_id', $lead->id)->firstOrFail();
        $this->assertSame($admin->id, $interacao->user_id);
        $this->assertSame('Site — Manutenção Industrial', $interacao->channel);
        $this->assertStringContainsString('Nº de técnicos: 6 a 15', $interacao->summary);

        // Sino: só o admin é avisado quando existe admin no tenant.
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('Novo lead do site', $admin->notifications()->first()->data['title']);
        $this->assertStringContainsString('Empresa A', $admin->notifications()->first()->data['body']);
        $this->assertSame(0, $comum->notifications()->count());
    }

    public function test_sem_admin_avisa_os_usuarios_aprovados_e_ignora_nao_aprovados(): void
    {
        $aprovado = $this->makeUser($this->tenant, 'Aprovado');
        $pendente = $this->makeUser($this->tenant, 'Pendente', approved: false);

        $this->send($this->payload())->assertStatus(201);

        $this->assertSame(1, $aprovado->notifications()->count());
        $this->assertSame(0, $pendente->notifications()->count());
    }

    public function test_rotulos_opcionais_tem_padrao(): void
    {
        $this->makeUser($this->tenant, 'Admin', admin: true);

        $payload = $this->payload();
        unset($payload['porte_label'], $payload['origem_label']);
        $this->send($payload)->assertStatus(201);

        $interacao = CrmLeadInteraction::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('Site — manutencao-industrial', $interacao->channel);
        $this->assertStringContainsString('Porte: 6 a 15', $interacao->summary);
    }

    public function test_mensagem_livre_opcional_entra_na_interacao(): void
    {
        $this->makeUser($this->tenant, 'Admin', admin: true);

        $payload = $this->payload(['mensagem' => 'Hoje controlamos tudo em planilha, queremos avaliar o sistema.']);
        $this->send($payload)->assertStatus(201);

        $interacao = CrmLeadInteraction::withoutGlobalScopes()->firstOrFail();
        $this->assertStringContainsString('Mensagem do visitante:', $interacao->summary);
        $this->assertStringContainsString('Hoje controlamos tudo em planilha, queremos avaliar o sistema.', $interacao->summary);
    }

    public function test_mensagem_ausente_nao_aparece_na_interacao(): void
    {
        $this->makeUser($this->tenant, 'Admin', admin: true);

        $this->send($this->payload())->assertStatus(201);

        $interacao = CrmLeadInteraction::withoutGlobalScopes()->firstOrFail();
        $this->assertStringNotContainsString('Mensagem do visitante:', $interacao->summary);
    }

    public function test_mensagem_acima_do_limite_devolve_422_e_nao_grava(): void
    {
        $this->makeUser($this->tenant, 'Admin', admin: true);

        $this->send($this->payload(['mensagem' => str_repeat('x', 2001)]))->assertStatus(422);

        $this->assertSame(0, $this->leadsOf($this->tenant)->count());
    }

    public function test_lead_sem_usuarios_no_tenant_ainda_e_salvo(): void
    {
        $this->send($this->payload())->assertStatus(201);

        $this->assertSame(1, $this->leadsOf($this->tenant)->count());
        $this->assertSame(0, CrmLeadInteraction::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_mesmo_lead_id_nao_duplica(): void
    {
        $this->makeUser($this->tenant, 'Admin', admin: true);
        $payload = $this->payload(['lead_id' => 'abc123']);

        $this->send($payload)->assertStatus(201);
        $this->send($payload)->assertStatus(200)->assertJsonPath('duplicate', true);

        $this->assertSame(1, $this->leadsOf($this->tenant)->count());
    }

    public function test_tenant_do_canal_inexistente_devolve_503_e_nao_vaza_para_outro_tenant(): void
    {
        // Um tenant de cliente existe; se o slug do canal sumir, o lead NÃO pode cair nele.
        $cliente = $this->makeTenant('cliente-'.uniqid());
        $this->makeUser($cliente, 'Cliente', admin: true);
        config(['services.inbound.channels.site-oravel.tenant_slug' => 'slug-que-nao-existe']);

        $this->send($this->payload())->assertStatus(503);

        $this->assertSame(0, $this->leadsOf($cliente)->count());
        $this->assertSame(0, $this->leadsOf($this->tenant)->count());
    }

    /** O ponto do multi-tenant: cada token só alcança o seu tenant, e o corpo nunca escolhe tenant. */
    public function test_dois_canais_dois_tenants_cada_token_so_alcanca_o_seu(): void
    {
        $outro = $this->makeTenant('cliente-b-'.uniqid());
        $adminA = $this->makeUser($this->tenant, 'AdminA', admin: true);
        $adminB = $this->makeUser($outro, 'AdminB', admin: true);
        config(['services.inbound.channels.site-cliente-b' => ['token' => 'token-de-teste-canal-b', 'tenant_slug' => $outro->slug]]);

        $this->send($this->payload(['company' => 'Lead do canal A']), token: self::TOKEN)->assertStatus(201);
        $this->send($this->payload(['company' => 'Lead do canal B']), token: 'token-de-teste-canal-b')->assertStatus(201);

        $this->assertSame(['Lead do canal A'], $this->leadsOf($this->tenant)->pluck('company_name')->all());
        $this->assertSame(['Lead do canal B'], $this->leadsOf($outro)->pluck('company_name')->all());

        // O sino de cada tenant só toca para o próprio tenant.
        $this->assertSame(1, $adminA->notifications()->count());
        $this->assertSame(1, $adminB->notifications()->count());
        $this->assertStringContainsString('Lead do canal A', $adminA->notifications()->first()->data['body']);
        $this->assertStringContainsString('Lead do canal B', $adminB->notifications()->first()->data['body']);

        // Mandar tenant_id/tenant_slug no corpo não muda nada: quem manda é o token.
        $this->send($this->payload(['company' => 'Tentativa', 'tenant_id' => $outro->id, 'tenant_slug' => $outro->slug]), token: self::TOKEN)->assertStatus(201);
        $this->assertSame(2, $this->leadsOf($this->tenant)->count());
        $this->assertSame(1, $this->leadsOf($outro)->count());
    }

    public function test_o_mesmo_lead_id_em_canais_diferentes_nao_colide(): void
    {
        $outro = $this->makeTenant('cliente-c-'.uniqid());
        config(['services.inbound.channels.site-cliente-c' => ['token' => 'token-de-teste-canal-c', 'tenant_slug' => $outro->slug]]);
        $payload = $this->payload(['lead_id' => 'mesmo-id']);

        $this->send($payload, token: self::TOKEN)->assertStatus(201);
        $this->send($payload, token: 'token-de-teste-canal-c')->assertStatus(201);

        $this->assertSame(1, $this->leadsOf($this->tenant)->count());
        $this->assertSame(1, $this->leadsOf($outro)->count());
    }
}
