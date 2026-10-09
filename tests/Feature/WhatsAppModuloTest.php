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
use App\Models\WhatsappNumero;
use App\Services\AvisosWhatsApp;
use App\Services\WhatsAppEmpresaService;
use App\Support\MenuModules;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppModuloTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(bool $comModulo): array
    {
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['modulo_whatsapp' => $comModulo]]);
        $tenant = Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@t.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]));
        $config = TenantWhatsappSetting::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'enabled' => true, 'access_token' => 'T', 'app_secret' => 'S', 'verify_token' => 'V', 'aviso_cobranca' => true, 'template_cobranca' => 'x']);
        $numero = WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'user_id' => null, 'phone_number_id' => 'N-'.uniqid()]);

        return [$tenant, $admin, $config, $numero->refresh()];
    }

    public function test_o_modulo_aparece_na_central_e_nasce_desligado(): void
    {
        $opcoes = collect(\App\Filament\Central\Resources\ContratoResource::groupedFeatureOptions())->flatMap(fn ($o) => array_keys($o))->all();
        $this->assertContains('modulo_whatsapp', $opcoes);

        // recurso não é tela: nunca vira item de menu nem trava rota
        $this->assertNull(MenuModules::keyForSlug('modulo-whatsapp-sem-rota'));
        $this->assertNull(MenuModules::keyForItem('Comercial', 'WhatsApp (módulo)'));
    }

    public function test_sem_o_modulo_nada_do_whatsapp_funciona(): void
    {
        Http::fake();
        [$tenant, $admin, $config, $numero] = $this->empresa(false);
        $this->actingAs($admin);

        $this->assertFalse(WhatsAppEmpresaService::moduloLiberado($tenant->id));
        $this->assertFalse(CaixaWhatsApp::canAccess());
        $this->assertFalse(WhatsAppDaEmpresa::canAccess());
        $this->assertNull(WhatsAppEmpresaService::paraNumero($numero));
        $this->assertNull(WhatsAppEmpresaService::paraUsuario($admin));
        $this->assertSame(0, AvisosWhatsApp::cobrancas($config));

        // webhook recusado, mesmo com assinatura certa e token certo
        $json = json_encode(['entry' => [['changes' => [['value' => ['metadata' => ['phone_number_id' => $numero->phone_number_id], 'messages' => [['from' => '5519900000001', 'id' => 'wamid.1', 'type' => 'text', 'text' => ['body' => 'oi']]]]]]]]]);
        $assinatura = 'sha256='.hash_hmac('sha256', $json, 'S');
        $this->call('POST', '/api/webhooks/whatsapp-empresa/'.$tenant->id, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $assinatura], $json)->assertForbidden();
        $this->get('/api/webhooks/whatsapp-empresa/'.$tenant->id.'?hub_mode=subscribe&hub_verify_token=V&hub_challenge=ABC')->assertForbidden();

        $this->assertSame(0, WhatsappConversa::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        Http::assertNothingSent();
    }

    public function test_com_o_modulo_liberado_tudo_volta(): void
    {
        [$tenant, $admin, $config, $numero] = $this->empresa(true);
        $this->actingAs($admin);

        $this->assertTrue(WhatsAppEmpresaService::moduloLiberado($tenant->id));
        $this->assertTrue(CaixaWhatsApp::canAccess());
        $this->assertTrue(WhatsAppDaEmpresa::canAccess());
        $this->assertNotNull(WhatsAppEmpresaService::paraNumero($numero));
    }
}
