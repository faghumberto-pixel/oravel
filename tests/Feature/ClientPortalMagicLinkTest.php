<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use App\Notifications\ClientMagicLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Portal do Cliente: "entrar sem senha" via link mágico enviado por e-mail.
 * Cobre o que realmente importa num fluxo de auth alternativo: não vazar
 * se um e-mail existe, o link efetivamente logar no guard certo, e o
 * token não poder ser reutilizado depois do primeiro uso.
 */
class ClientPortalMagicLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClient(bool $portalEnabled = true): array
    {
        $plan = Plan::create([
            'name' => 'Plano MagicLink '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant MagicLink '.uniqid(), 'slug' => 'tenant-magiclink-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente MagicLink',
            'email' => 'magiclink-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => $portalEnabled ? now() : null,
        ]);

        return [$tenant, $client];
    }

    public function test_requesting_link_for_valid_client_sends_notification(): void
    {
        Notification::fake();

        [, $client] = $this->makeTenantWithClient();

        $response = $this->post(route('cliente.magic-link.send'), ['email' => $client->email]);

        $response->assertRedirect();
        Notification::assertSentTo($client, ClientMagicLinkNotification::class);
    }

    public function test_requesting_link_for_client_without_portal_access_sends_no_notification(): void
    {
        Notification::fake();

        [, $client] = $this->makeTenantWithClient(portalEnabled: false);

        $response = $this->post(route('cliente.magic-link.send'), ['email' => $client->email]);

        // Mesma resposta de "sucesso" genérico -- não revela se o e-mail existe.
        $response->assertRedirect();
        Notification::assertNothingSent();
    }

    public function test_requesting_link_for_unknown_email_sends_no_notification_but_still_redirects(): void
    {
        Notification::fake();

        $response = $this->post(route('cliente.magic-link.send'), ['email' => 'nao-existe@teste.com']);

        $response->assertRedirect();
        Notification::assertNothingSent();
    }

    public function test_magic_link_logs_client_in(): void
    {
        [, $client] = $this->makeTenantWithClient();

        $capturedUrl = null;
        Notification::fake();

        $this->post(route('cliente.magic-link.send'), ['email' => $client->email]);

        Notification::assertSentTo($client, ClientMagicLinkNotification::class, function ($notification) use (&$capturedUrl) {
            $capturedUrl = (fn () => $this->signedUrl)->call($notification);

            return true;
        });

        $this->assertNotNull($capturedUrl);

        $response = $this->get($capturedUrl);

        $response->assertRedirect('/cliente');
        $this->assertTrue(Auth::guard('client')->check());
        $this->assertSame($client->id, Auth::guard('client')->id());
    }

    public function test_magic_link_cannot_be_used_twice(): void
    {
        [, $client] = $this->makeTenantWithClient();

        $capturedUrl = null;
        Notification::fake();

        $this->post(route('cliente.magic-link.send'), ['email' => $client->email]);

        Notification::assertSentTo($client, ClientMagicLinkNotification::class, function ($notification) use (&$capturedUrl) {
            $capturedUrl = (fn () => $this->signedUrl)->call($notification);

            return true;
        });

        // Primeiro uso: loga normalmente.
        $this->get($capturedUrl)->assertRedirect('/cliente');

        Auth::guard('client')->logout();

        // Segundo uso do mesmo link: token já foi consumido no primeiro.
        $response = $this->get($capturedUrl);

        $response->assertForbidden();
        $this->assertFalse(Auth::guard('client')->check());
    }

    public function test_tampered_magic_link_is_rejected(): void
    {
        [, $client] = $this->makeTenantWithClient();

        Notification::fake();
        $this->post(route('cliente.magic-link.send'), ['email' => $client->email]);

        $response = $this->get(route('cliente.magic-link.login', ['token' => 'token-forjado-qualquer']));

        $response->assertForbidden();
        $this->assertFalse(Auth::guard('client')->check());
    }
}
