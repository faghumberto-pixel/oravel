<?php

namespace Tests\Feature;

use App\Models\CrmConta;
use App\Models\CrmLead;
use App\Models\CrmProposta;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Fase 7A: o que a extensão do WhatsApp precisa da API (propostas do CRM) e o download do pacote. */
class CrmExtensaoApiTest extends TestCase
{
    use DatabaseTransactions;

    private const BASE = '/api/crm/v1';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('x');
        Cache::flush();
    }

    private function tenant(?array $features = null): Tenant
    {
        $plan = Plan::create(['name' => 'Plano Ext '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => $features ?? ['tabela_crm_leads' => true, 'tabela_crm_contas' => true, 'tabela_crm_propostas' => true]]);

        return Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function usuario(Tenant $t, string $papel, array $permissoes = []): User
    {
        $u = User::create(['name' => ucfirst($papel).' '.uniqid(), 'email' => $papel[0].'-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web', 'tenant_id' => $t->id]));
        foreach ($permissoes as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $u->givePermissionTo($p);
        }

        return $u;
    }

    private function vendedor(Tenant $t): User
    {
        return $this->usuario($t, 'comercial', ['ler_crm_lead', 'criar_crm_lead', 'editar_crm_lead', 'ler_crm_conta', 'ler_crm_proposta']);
    }

    private function api(User $u): self
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this->withToken($u->createToken('teste', ['crm:read', 'crm:write'], now()->addDays(30))->plainTextToken);
    }

    private function proposta(Tenant $t, User $v, array $extra = []): CrmProposta
    {
        return CrmProposta::create($extra + ['tenant_id' => $t->id, 'vendedor_user_id' => $v->id, 'titulo' => 'Proposta '.uniqid()]);
    }

    public function test_opportunities_include_the_crm_own_proposals_in_progress_for_an_account(): void
    {
        $t = $this->tenant();
        $admin = $this->usuario($t, 'admin');
        $conta = CrmConta::create(['tenant_id' => $t->id, 'nome' => 'Conta']);
        $this->proposta($t, $admin, ['crm_conta_id' => $conta->id, 'titulo' => 'Rascunho']);
        $enviada = $this->proposta($t, $admin, ['crm_conta_id' => $conta->id, 'titulo' => 'Enviada', 'status' => CrmProposta::STATUS_ENVIADA]);
        $this->proposta($t, $admin, ['crm_conta_id' => $conta->id, 'status' => CrmProposta::STATUS_ACEITA]);
        $this->proposta($t, $admin, ['crm_conta_id' => $conta->id, 'status' => CrmProposta::STATUS_RECUSADA]);
        $this->proposta($t, $admin, ['crm_conta_id' => $conta->id, 'status' => CrmProposta::STATUS_EXPIRADA]);

        $r = $this->api($admin)->getJson(self::BASE."/contatos/conta/{$conta->id}/oportunidades")->assertOk();
        $props = collect($r->json('data.propostas_em_andamento'));

        $this->assertCount(2, $props);
        $this->assertSame(['crm'], $props->pluck('origem')->unique()->values()->all());
        $achada = $props->firstWhere('id', $enviada->id);
        $this->assertSame($enviada->numero, $achada['numero']);
        $this->assertSame('Enviada', $achada['titulo']);
        $this->assertSame('Enviada', $achada['status_rotulo']);
        $this->assertStringContainsString('/admin/', $achada['url']);
    }

    public function test_lead_opportunities_and_lead_sheet_list_crm_proposals_without_erp_module(): void
    {
        $t = $this->tenant();   // pacote Vendas: sem proposta comercial do ERP
        $admin = $this->usuario($t, 'admin');
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Maria']);
        $this->proposta($t, $admin, ['crm_lead_id' => $lead->id, 'titulo' => 'Para a Maria']);

        $r = $this->api($admin)->getJson(self::BASE."/contatos/lead/{$lead->id}/oportunidades")->assertOk();
        $this->assertSame(['Para a Maria'], collect($r->json('data.propostas_em_andamento'))->pluck('titulo')->all());

        $ficha = $this->api($admin)->getJson(self::BASE."/leads/{$lead->id}")->assertOk();
        $this->assertSame(['crm'], collect($ficha->json('data.propostas_em_andamento'))->pluck('origem')->all());
    }

    public function test_erp_proposals_are_tagged_and_coexist_with_crm_ones(): void
    {
        $t = $this->tenant(['tabela_crm_leads' => true, 'tabela_crm_contas' => true, 'tabela_crm_propostas' => true, 'tabela_proposta_comercial' => true]);
        $admin = $this->usuario($t, 'admin');
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Maria']);
        PropostaComercial::create(['tenant_id' => $t->id, 'crm_lead_id' => $lead->id, 'seller_user_id' => $admin->id, 'status' => PropostaComercial::STATUS_RASCUNHO]);
        $this->proposta($t, $admin, ['crm_lead_id' => $lead->id]);

        $r = $this->api($admin)->getJson(self::BASE."/contatos/lead/{$lead->id}/oportunidades")->assertOk();

        $this->assertEqualsCanonicalizing(['erp', 'crm'], collect($r->json('data.propostas_em_andamento'))->pluck('origem')->all());
    }

    public function test_seller_only_sees_own_crm_proposals_and_other_tenants_never(): void
    {
        $t = $this->tenant();
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Maria', 'assigned_user_id' => $a->id]);
        $this->proposta($t, $a, ['crm_lead_id' => $lead->id, 'titulo' => 'Da A']);
        $this->proposta($t, $b, ['crm_lead_id' => $lead->id, 'titulo' => 'Da B']);

        $r = $this->api($a)->getJson(self::BASE."/contatos/lead/{$lead->id}/oportunidades")->assertOk();
        $this->assertSame(['Da A'], collect($r->json('data.propostas_em_andamento'))->pluck('titulo')->all());

        $this->api($b)->getJson(self::BASE."/contatos/lead/{$lead->id}/oportunidades")->assertStatus(404);   // lead de outro vendedor

        $outra = $this->tenant();
        $this->api($this->usuario($outra, 'admin'))->getJson(self::BASE."/contatos/lead/{$lead->id}/oportunidades")->assertStatus(404);
    }

    public function test_tenant_without_the_proposals_module_gets_none(): void
    {
        $t = $this->tenant(['tabela_crm_leads' => true, 'tabela_crm_contas' => true]);
        $admin = $this->usuario($t, 'admin');
        $conta = CrmConta::create(['tenant_id' => $t->id, 'nome' => 'Conta']);
        $this->proposta($t, $admin, ['crm_conta_id' => $conta->id]);

        $r = $this->api($admin)->getJson(self::BASE."/contatos/conta/{$conta->id}/oportunidades")->assertOk();

        $this->assertSame([], $r->json('data.propostas_em_andamento'));
    }

    public function test_extension_download_needs_login_the_crm_module_and_a_built_package(): void
    {
        $dir = storage_path('app/extensao');
        $zip = $dir.'/oravel-crm-whatsapp-9.9.9-teste.zip';
        File::ensureDirectoryExists($dir);

        // Pacotes reais já gerados por build.sh saem do caminho durante o teste e voltam no fim.
        $reais = [];
        foreach (glob($dir.'/oravel-crm-whatsapp-*.zip') ?: [] as $real) {
            File::move($real, $real.'.guardado');
            $reais[] = $real;
        }

        try {
            File::put($zip, 'PK-fake');
            $t = $this->tenant();
            $url = route('crm.extensao.download');

            $this->get($url)->assertRedirect();   // visitante vai para o login

            $this->actingAs($this->usuario($t, 'admin'))->get($url)->assertOk()->assertDownload('oravel-crm-whatsapp-9.9.9-teste.zip');

            $semModulo = $this->tenant(['tabela_clients' => true]);
            $this->actingAs($this->usuario($semModulo, 'admin'))->get($url)->assertForbidden();

            File::delete($zip);
            $this->actingAs($this->usuario($t, 'admin'))->get($url)->assertNotFound();
        } finally {
            File::delete($zip);

            foreach ($reais as $real) {
                File::move($real.'.guardado', $real);
            }
        }
    }
}
