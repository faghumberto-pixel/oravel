<?php

namespace Tests\Feature;

use App\Models\DocumentSignature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-09-23: "esse link de checkout tem que estar
 * diponivel na tela caso o cliente só faça o cadastro e deixe para pagar
 * depois, e nao tenha o link para o checkout" -- página de recuperação
 * por e-mail, sem precisar guardar token nenhum.
 */
class CheckoutLinkRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithAdmin(string $email, bool $approved = false): array
    {
        $plan = Plan::create([
            'name' => 'Plano Recover '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);

        $tenant = Tenant::create([
            'name' => 'Empresa Recover '.uniqid(), 'slug' => 'empresa-recover-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'trial',
        ]);

        $admin = User::create([
            'name' => 'Admin Recover', 'email' => $email,
            'password' => bcrypt('senha12345'), 'tenant_id' => $tenant->id, 'role' => 'admin', 'hourly_rate' => 0,
        ]);
        $admin->forceFill(['is_approved' => $approved])->save();

        return [$tenant, $admin];
    }

    public function test_recovers_checkout_link_for_signed_but_unpaid_tenant(): void
    {
        [$tenant, $admin] = $this->makeTenantWithAdmin('fechei-antes-de-pagar@oravel.com.br');

        $signature = DocumentSignature::create([
            'tenant_id' => $tenant->id,
            'signable_type' => Tenant::class,
            'signable_id' => $tenant->id,
            'signer_name' => 'Admin Recover',
            'signer_email' => $admin->email,
        ]);
        $signature->markAsSigned();

        $response = $this->post('/assinar/recuperar', ['email' => $admin->email]);

        $response->assertRedirect(route('checkout.continue', ['token' => $signature->token]));
    }

    public function test_redirects_to_signature_page_when_not_signed_yet(): void
    {
        [$tenant, $admin] = $this->makeTenantWithAdmin('nao-assinou-ainda@oravel.com.br');

        $signature = app(SignatureService::class)->generateSignatureLink($tenant, [
            'name' => 'Admin Recover', 'email' => $admin->email,
        ]);
        $token = basename(parse_url($signature, PHP_URL_PATH));

        $response = $this->post('/assinar/recuperar', ['email' => $admin->email]);

        $response->assertRedirect(route('signature.sign', ['token' => $token]));
    }

    public function test_tells_already_approved_admin_to_just_login(): void
    {
        // Uma vez pago, o fluxo de recuperação "pode sumir" (pedido do
        // usuário) -- não deve gerar mais nenhum link de pagamento.
        [, $admin] = $this->makeTenantWithAdmin('ja-pago@oravel.com.br', approved: true);

        $response = $this->post('/assinar/recuperar', ['email' => $admin->email]);

        $response->assertSessionHasErrors('email');
        $response->assertRedirect();
        $this->assertStringContainsString('já está liberado', session('errors')->first('email'));
    }

    public function test_unknown_email_shows_generic_error(): void
    {
        $response = $this->post('/assinar/recuperar', ['email' => 'nao-existe@oravel.com.br']);

        $response->assertSessionHasErrors('email');
    }
}
