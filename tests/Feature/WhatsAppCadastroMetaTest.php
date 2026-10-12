<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantWhatsappSetting;
use App\Models\WhatsappMensagem;
use App\Models\WhatsappNumero;
use App\Services\WhatsAppCadastroMeta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Cadastro incorporado da Meta + webhook único da Oravel (um app, várias empresas). */
class WhatsAppCadastroMetaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.app_id' => 'APP-1', 'services.whatsapp.app_secret' => 'segredo-da-oravel',
            'services.whatsapp.embedded_config_id' => 'CFG-1', 'services.whatsapp.webhook_token' => 'verifica-oravel',
            'services.whatsapp.base_url' => 'https://graph.facebook.com/v20.0',
        ]);
    }

    private function empresa(bool $modulo = true): Tenant
    {
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $modulo ? ['modulo_whatsapp' => true] : []]);

        return Tenant::create(['name' => 'Locadora '.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function fakeMeta(): void
    {
        Http::fake([
            '*/oauth/access_token*' => Http::response(['access_token' => 'TOKEN-DO-CLIENTE']),
            '*/register' => Http::response(['success' => true]),
            '*/subscribed_apps' => Http::response(['success' => true]),
            '*/PNID-1*' => Http::response(['display_phone_number' => '+55 77 90000-1111', 'verified_name' => 'Locadora Teste']),
        ]);
    }

    private function postar(array $payload, string $segredo = 'segredo-da-oravel', string $url = '/api/webhooks/whatsapp-meta')
    {
        $json = json_encode($payload);

        return $this->call('POST', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, $segredo),
        ], $json);
    }

    private function mensagemDe(string $phoneNumberId, string $waId = 'wamid.1'): array
    {
        return ['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => $phoneNumberId],
            'contacts' => [['wa_id' => '5577988887777', 'profile' => ['name' => 'Cliente']]],
            'messages' => [['id' => $waId, 'from' => '5577988887777', 'type' => 'text', 'text' => ['body' => 'Olá, quero alugar']]],
        ]]]]]];
    }

    public function test_concluir_saves_token_number_and_enables_whatsapp(): void
    {
        $this->fakeMeta();
        $tenant = $this->empresa();

        $numero = app(WhatsAppCadastroMeta::class)->concluir($tenant->id, 'CODIGO', 'WABA-1', 'PNID-1');

        $config = TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();
        $this->assertTrue($config->enabled);
        $this->assertSame('TOKEN-DO-CLIENTE', $config->access_token);
        $this->assertSame('WABA-1', $config->waba_id);
        $this->assertNull($config->app_secret);
        $this->assertSame('PNID-1', $numero->phone_number_id);
        $this->assertSame('+55 77 90000-1111', $numero->display_phone);
        $this->assertTrue($numero->last_test_ok);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'WABA-1/subscribed_apps'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'PNID-1/register'));
    }

    public function test_refused_code_saves_nothing(): void
    {
        Http::fake(['*/oauth/access_token*' => Http::response(['error' => ['message' => 'Código inválido']], 400)]);
        $tenant = $this->empresa();

        try {
            app(WhatsAppCadastroMeta::class)->concluir($tenant->id, 'RUIM', 'WABA-1', 'PNID-1');
            $this->fail('deveria lançar');
        } catch (\RuntimeException $e) {
            $this->assertSame('Código inválido', $e->getMessage());
        }

        $this->assertSame(0, TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, WhatsappNumero::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_unavailable_without_platform_app_configuration(): void
    {
        config(['services.whatsapp.embedded_config_id' => null]);

        $this->assertFalse(WhatsAppCadastroMeta::disponivel());
        $this->expectException(\RuntimeException::class);
        app(WhatsAppCadastroMeta::class)->concluir($this->empresa()->id, 'C', 'W', 'P');
    }

    public function test_platform_webhook_verification_uses_global_token(): void
    {
        $this->get('/api/webhooks/whatsapp-meta?hub_mode=subscribe&hub_verify_token=verifica-oravel&hub_challenge=ola')->assertOk()->assertSee('ola');
        $this->get('/api/webhooks/whatsapp-meta?hub_mode=subscribe&hub_verify_token=errado&hub_challenge=ola')->assertForbidden();
    }

    public function test_platform_webhook_routes_message_to_the_company_that_owns_the_number(): void
    {
        $this->fakeMeta();
        $a = $this->empresa();
        $b = $this->empresa();
        app(WhatsAppCadastroMeta::class)->concluir($a->id, 'C', 'WABA-A', 'PNID-1');
        TenantWhatsappSetting::withoutGlobalScopes()->create(['tenant_id' => $b->id, 'enabled' => true, 'access_token' => 'x', 'verify_token' => 'v']);
        WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $b->id, 'phone_number_id' => 'PNID-B', 'enabled' => true]);

        $this->postar($this->mensagemDe('PNID-1'))->assertOk();

        $this->assertSame(1, WhatsappMensagem::withoutGlobalScopes()->where('tenant_id', $a->id)->count());
        $this->assertSame(0, WhatsappMensagem::withoutGlobalScopes()->where('tenant_id', $b->id)->count());
    }

    public function test_platform_webhook_rejects_bad_signature_and_ignores_unknown_or_disabled_module(): void
    {
        $this->fakeMeta();
        $a = $this->empresa();
        app(WhatsAppCadastroMeta::class)->concluir($a->id, 'C', 'WABA-A', 'PNID-1');

        $this->postar($this->mensagemDe('PNID-1'), 'segredo-errado')->assertForbidden();
        $this->postar($this->mensagemDe('PNID-DESCONHECIDO', 'wamid.2'))->assertOk();

        $semModulo = $this->empresa(false);
        TenantWhatsappSetting::withoutGlobalScopes()->create(['tenant_id' => $semModulo->id, 'enabled' => true, 'access_token' => 'x', 'verify_token' => 'v']);
        WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $semModulo->id, 'phone_number_id' => 'PNID-SEM', 'enabled' => true]);
        $this->postar($this->mensagemDe('PNID-SEM', 'wamid.3'))->assertOk();

        $this->assertSame(0, WhatsappMensagem::withoutGlobalScopes()->count());
    }

    public function test_company_url_accepts_platform_secret_when_company_has_no_own_secret(): void
    {
        $this->fakeMeta();
        $a = $this->empresa();
        app(WhatsAppCadastroMeta::class)->concluir($a->id, 'C', 'WABA-A', 'PNID-1');

        $this->postar($this->mensagemDe('PNID-1', 'wamid.9'), 'segredo-da-oravel', '/api/webhooks/whatsapp-empresa/'.$a->id)->assertOk();

        $this->assertSame(1, WhatsappMensagem::withoutGlobalScopes()->where('tenant_id', $a->id)->count());
    }
}
