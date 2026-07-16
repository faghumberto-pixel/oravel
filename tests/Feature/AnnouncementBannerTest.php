<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementBannerTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Teste', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Aviso', 'slug' => 'tenant-aviso-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeTenantAdmin(Tenant $tenant): User
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'email_verified_at' => now(), 'is_approved' => true,
        ]);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Regressao: o render hook do banner (PanelsRenderHook::BODY_START) roda
     * em toda pagina do painel, inclusive a de login -- sem checar
     * auth()->check(), um aviso global (target_tenant_id nulo) aparecia pra
     * visitante nao autenticado, o que nao faz sentido (login nao tem
     * tenant nenhum associado).
     */
    public function test_global_announcement_does_not_show_on_login_page(): void
    {
        Announcement::create([
            'title' => 'Aviso Global',
            'message' => 'Mensagem de teste.',
            'level' => 'info',
            'target_tenant_id' => null,
            'is_active' => true,
        ]);

        $response = $this->get('/admin/login');

        $response->assertDontSee('Aviso Global');
    }

    public function test_global_announcement_shows_for_authenticated_tenant_admin(): void
    {
        $tenant = $this->makeTenant();
        Announcement::create([
            'title' => 'Aviso Global',
            'message' => 'Mensagem de teste.',
            'level' => 'info',
            'target_tenant_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($this->makeTenantAdmin($tenant));

        $response = $this->get('/admin/assets');

        $response->assertSee('Aviso Global');
    }

    public function test_tenant_specific_announcement_does_not_leak_to_other_tenants(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant();

        Announcement::create([
            'title' => 'Aviso So Do Tenant A',
            'message' => 'Mensagem de teste.',
            'level' => 'critical',
            'target_tenant_id' => $tenantA->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->makeTenantAdmin($tenantB));

        $response = $this->get('/admin/assets');

        $response->assertDontSee('Aviso So Do Tenant A');
    }

    /**
     * Regressao: o x-data do banner usava aspas duplas por fora
     * (x-data="...") enquanto @json($items) por dentro tambem gera aspas
     * duplas (chaves/valores JSON) -- o navegador fechava o atributo na
     * primeira aspa do JSON, e o resto do Alpine (dismiss(), x-show="active",
     * class="...", etc.) vazava como texto visivel na pagina, logo abaixo do
     * aviso. assertSee/assertDontSee simples nao pegam isso porque o texto
     * bruto do markup e identico com ou sem o bug -- so um parser de HTML
     * revela que o texto ficou fora de qualquer atributo. Corrigido trocando
     * pra aspas simples (x-data='...'), que nao colidem com o JSON interno.
     * Tambem cobre o caso de 2 avisos simultaneos (carrossel), nunca testado
     * antes.
     */
    public function test_banner_markup_does_not_leak_alpine_code_as_visible_text(): void
    {
        $tenant = $this->makeTenant();
        Announcement::create([
            'title' => 'Aviso Um', 'message' => 'Primeira mensagem.',
            'level' => 'info', 'target_tenant_id' => null, 'is_active' => true,
        ]);
        Announcement::create([
            'title' => 'Aviso Dois', 'message' => 'Segunda mensagem.',
            'level' => 'warning', 'target_tenant_id' => null, 'is_active' => true,
        ]);

        $this->actingAs($this->makeTenantAdmin($tenant));

        $response = $this->get('/admin/assets');
        $response->assertOk();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $leakedTextNodes = $xpath->query("//text()[contains(., 'x-show') or contains(., 'dismissedIds') or contains(., 'setInterval')]");

        $this->assertSame(
            0,
            $leakedTextNodes->length,
            'Alpine JS/attribute markup leaked as visible text on the page.'
        );
    }
}
