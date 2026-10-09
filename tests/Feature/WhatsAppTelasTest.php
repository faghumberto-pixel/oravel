<?php

namespace Tests\Feature;

use App\Filament\Pages\CaixaWhatsApp;
use App\Filament\Pages\WhatsAppDaEmpresa;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantWhatsappSetting;
use App\Models\User;
use App\Models\WhatsappConversa;
use App\Models\WhatsappMensagem;
use App\Models\WhatsappNumero;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppTelasTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(): array
    {
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $tenant = Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = $this->usuario($tenant, 'Admin', admin: true);

        return [$tenant, $admin];
    }

    private function usuario(Tenant $tenant, string $nome, bool $admin = false): User
    {
        $u = User::create(['name' => $nome, 'email' => uniqid().'@t.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        if ($admin) {
            $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]));
        }

        return $u;
    }

    private function ligar(Tenant $tenant): TenantWhatsappSetting
    {
        return TenantWhatsappSetting::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'enabled' => true, 'access_token' => 'TOK', 'app_secret' => 'SEG', 'verify_token' => 'VT', 'template_abertura' => 'abertura']);
    }

    public function test_admin_cadastra_numeros_por_usuario_e_liga_quando_a_meta_aceita(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['display_phone_number' => '+55 19 99999-1111', 'verified_name' => 'Loc'], 200)]);
        [$tenant, $admin] = $this->empresa();
        $ana = $this->usuario($tenant, 'Ana');
        $this->actingAs($admin);

        Livewire::test(WhatsAppDaEmpresa::class)
            ->fillForm(['access_token' => 'TOKEN-1', 'app_secret' => 'SEGREDO-1', 'template_language' => 'pt_BR', 'numeros' => [
                ['user_id' => $ana->id, 'phone_number_id' => 'N-ANA', 'rotulo' => null],
                ['user_id' => null, 'phone_number_id' => 'N-EMP', 'rotulo' => 'Recepção'],
            ]])
            ->call('testarEAtivar')
            ->assertNotified();

        $config = TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertTrue($config->enabled);
        $this->assertNotEmpty($config->verify_token);
        $numeros = WhatsappNumero::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
        $this->assertCount(2, $numeros);
        $this->assertSame($ana->id, $numeros->firstWhere('phone_number_id', 'N-ANA')->user_id);
        $this->assertTrue($numeros->every(fn ($n) => $n->last_test_ok));
    }

    public function test_meta_recusando_nao_liga(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401)]);
        [$tenant, $admin] = $this->empresa();
        $this->actingAs($admin);

        Livewire::test(WhatsAppDaEmpresa::class)
            ->fillForm(['access_token' => 'RUIM', 'app_secret' => 'S', 'template_language' => 'pt_BR', 'numeros' => [['user_id' => null, 'phone_number_id' => 'N-1']]])
            ->call('testarEAtivar');

        $this->assertFalse(TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail()->enabled);
    }

    public function test_caixa_mostra_so_as_conversas_do_proprio_numero_e_envia_por_ele(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]], 200)]);
        [$tenant, $admin] = $this->empresa();
        $this->ligar($tenant);
        $ana = $this->usuario($tenant, 'Ana');
        $bruno = $this->usuario($tenant, 'Bruno');
        $nAna = WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'user_id' => $ana->id, 'phone_number_id' => 'N-ANA']);
        $nBruno = WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'user_id' => $bruno->id, 'phone_number_id' => 'N-BRUNO']);
        $cAna = WhatsappConversa::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'numero_id' => $nAna->id, 'responsavel_user_id' => $ana->id, 'telefone' => '5519900000001', 'nome' => 'Cliente da Ana', 'ultima_recebida_em' => now(), 'ultima_mensagem_em' => now(), 'nao_lidas' => 2]);
        $cBruno = WhatsappConversa::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'numero_id' => $nBruno->id, 'responsavel_user_id' => $bruno->id, 'telefone' => '5519900000002', 'nome' => 'Cliente do Bruno', 'ultima_recebida_em' => now(), 'ultima_mensagem_em' => now()]);

        $this->actingAs($ana);

        $pagina = Livewire::test(CaixaWhatsApp::class);
        $pagina->assertSee('Cliente da Ana')->assertDontSee('Cliente do Bruno');

        $pagina->call('selecionar', $cAna->id)->set('texto', 'Olá, tudo bem?')->call('enviar');
        $this->assertSame(0, $cAna->fresh()->nao_lidas);
        $this->assertSame('enviada', WhatsappMensagem::withoutGlobalScopes()->where('conversa_id', $cAna->id)->where('direcao', 'saida')->value('status'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/N-ANA/messages') && $r['text']['body'] === 'Olá, tudo bem?');

        // não consegue abrir a conversa de outro
        $pagina->call('selecionar', $cBruno->id);
        $this->assertSame($cAna->id, $pagina->get('conversaId'));

        // admin vê todas quando pede
        $this->actingAs($admin);
        Livewire::test(CaixaWhatsApp::class)->set('verTodas', true)->assertSee('Cliente da Ana')->assertSee('Cliente do Bruno');
    }

    public function test_sem_whatsapp_ligado_a_tela_nao_aparece(): void
    {
        [$tenant, $admin] = $this->empresa();
        $this->actingAs($admin);

        $this->assertFalse(CaixaWhatsApp::canAccess());
        $this->ligar($tenant);
        $this->assertTrue(CaixaWhatsApp::canAccess());
    }
}
