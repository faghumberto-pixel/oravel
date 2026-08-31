<?php

namespace Tests\Feature;

use App\Mail\GenericPdfMail;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\EquipmentDamage;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PropostaComercialEmailTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Proposta Email '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial', 'tabela_solicitacao_locacao'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Proposta Email '.uniqid(), 'slug' => 'tenant-proposta-email-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function makePropostaComItem(Tenant $tenant, User $seller, ?string $clientEmail = 'cliente@example.com'): PropostaComercial
    {
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Email', 'email' => $clientEmail]);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras']);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $seller->id,
        ]);
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Empilhadeira', 'quantity' => 1, 'unit_price' => 500,
        ]);

        return $proposta->fresh();
    }

    public function test_enviar_para_comercial_notifica_usuarios_com_role_comercial(): void
    {
        Mail::fake();
        [$tenant, $seller] = $this->makeTenantAdmin();

        $comercial = User::create([
            'name' => 'Usuário Comercial', 'email' => 'comercial-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $comercial->forceFill(['email_verified_at' => now()])->save();
        $comercial->assignRole(Role::firstOrCreate(['name' => EquipmentDamage::ROLE_COMERCIAL, 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $proposta = $this->makePropostaComItem($tenant, $seller);
        $proposta->enviarParaComercial();

        Mail::assertSent(GenericPdfMail::class, fn ($mail) => $mail->hasTo($comercial->email));
    }

    public function test_enviar_para_comercial_sem_ninguem_com_a_role_nao_lanca_erro(): void
    {
        Mail::fake();
        [$tenant, $seller] = $this->makeTenantAdmin();

        $proposta = $this->makePropostaComItem($tenant, $seller);
        $proposta->enviarParaComercial();

        Mail::assertNothingSent();
    }

    public function test_aprovar_envia_pdf_e_link_ao_cliente(): void
    {
        Mail::fake();
        [$tenant, $seller] = $this->makeTenantAdmin();

        $proposta = $this->makePropostaComItem($tenant, $seller, 'cliente-real@example.com');
        $proposta->enviarParaComercial();
        $proposta->refresh();
        $proposta->aprovar($seller);

        Mail::assertSent(GenericPdfMail::class, fn ($mail) => $mail->hasTo('cliente-real@example.com')
            && str_contains($mail->bodyText, route('proposta-comercial.public-approval', $proposta->fresh()->approval_token))
            && $mail->pdfContent !== null
        );
    }

    public function test_aprovar_sem_email_de_cliente_lanca_excecao_e_nao_envia_nada(): void
    {
        Mail::fake();
        [$tenant, $seller] = $this->makeTenantAdmin();

        $proposta = $this->makePropostaComItem($tenant, $seller, null);
        $proposta->enviarParaComercial();
        $proposta->refresh();

        $this->expectException(\RuntimeException::class);
        $proposta->aprovar($seller);

        Mail::assertNothingSent();
    }
}
