<?php

namespace Tests\Feature;

use App\Filament\Resources\PropostaComercialResource\Pages\CreatePropostaComercial;
use App\Filament\Resources\PropostaComercialResource\Pages\ListPropostaComerciais;
use App\Filament\Resources\PropostaComercialResource\Pages\ViewPropostaComercial;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tela do Comercial: aprova/rejeita via Filament Action, mesmo padrão de
 * ViewEquipmentDamage. Cobre a criação da SolicitacaoLocacao ao aprovar, e
 * o caso 100%-serviço bloqueando o acionamento automático com aviso.
 */
class PropostaComercialResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Resource Proposta '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial', 'tabela_solicitacao_locacao'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Resource Proposta', 'slug' => 'tenant-resource-proposta-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin Comercial', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function makePropostaEnviada(Tenant $tenant, Client $client, User $seller, bool $comEquipamento = true): PropostaComercial
    {
        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $seller->id,
        ]);

        if ($comEquipamento) {
            $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Gerador '.uniqid()]);
            $proposta->items()->create([
                'tenant_id' => $tenant->id, 'type' => PropostaComercialItem::TYPE_EQUIPAMENTO,
                'asset_category_id' => $category->id, 'description' => 'Gerador 180 kVA',
                'quantity' => 1, 'unit_price' => 5000,
            ]);
        }

        $proposta->items()->create([
            'tenant_id' => $tenant->id, 'type' => PropostaComercialItem::TYPE_SERVICO,
            'description' => 'Motorista', 'quantity' => 1, 'unit_price' => 500,
        ]);

        $proposta->enviarParaComercial();

        return $proposta;
    }

    public function test_comercial_aprova_via_action_muda_status_sem_criar_solicitacao(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste', 'email' => 'cliente-'.uniqid().'@teste.com']);
        $proposta = $this->makePropostaEnviada($tenant, $client, $admin);

        $this->actingAs($admin);

        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->id])
            ->callAction('aprovar')
            ->assertHasNoActionErrors();

        $proposta->refresh();
        $this->assertSame(PropostaComercial::STATUS_APROVADA_INTERNA, $proposta->status);
        $this->assertNull($proposta->solicitacao_locacao_id);
        $this->assertSame(0, SolicitacaoLocacao::count());

        $proposta->aceitarPeloCliente();
        $proposta->refresh();
        $this->assertNotNull($proposta->solicitacao_locacao_id);
        $this->assertSame(1, SolicitacaoLocacao::count());
    }

    public function test_comercial_rejeita_via_action_com_motivo(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);
        $proposta = $this->makePropostaEnviada($tenant, $client, $admin);

        $this->actingAs($admin);

        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->id])
            ->callAction('rejeitar', data: ['reason' => 'Fora do orçamento do cliente.'])
            ->assertHasNoActionErrors();

        $proposta->refresh();
        $this->assertSame(PropostaComercial::STATUS_REJEITADA, $proposta->status);
        $this->assertSame('Fora do orçamento do cliente.', $proposta->rejection_reason);
    }

    public function test_aprovar_proposta_100_por_cento_servico_nao_cria_solicitacao(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste', 'email' => 'cliente-'.uniqid().'@teste.com']);
        $proposta = $this->makePropostaEnviada($tenant, $client, $admin, comEquipamento: false);

        $this->actingAs($admin);

        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->id])
            ->callAction('aprovar')
            ->assertHasNoActionErrors();

        $proposta->refresh();
        $this->assertSame(PropostaComercial::STATUS_APROVADA_INTERNA, $proposta->status);
        $this->assertNull($proposta->solicitacao_locacao_id);
        $this->assertSame(0, SolicitacaoLocacao::count());
    }

    public function test_aprovar_action_nao_visivel_apos_ja_aprovada(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste', 'email' => 'cliente-'.uniqid().'@teste.com']);
        $proposta = $this->makePropostaEnviada($tenant, $client, $admin);
        $proposta->aprovar($admin);

        $this->actingAs($admin);

        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->id])
            ->assertActionHidden('aprovar')
            ->assertActionHidden('rejeitar');
    }

    public function test_admin_cria_proposta_pelo_desktop_com_itens(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste', 'email' => 'cliente-'.uniqid().'@teste.com']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira '.uniqid()]);

        $this->actingAs($admin);

        Livewire::test(CreatePropostaComercial::class)
            ->fillForm([
                'client_id' => $client->id,
                'seller_user_id' => $admin->id,
                'items' => [
                    [
                        'type' => PropostaComercialItem::TYPE_EQUIPAMENTO,
                        'asset_category_id' => $category->id,
                        'description' => 'Empilhadeira 2.5t',
                        'quantity' => 1,
                        'unit_price' => 3000,
                    ],
                ],
                'terms' => 'Termos de teste',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $proposta = PropostaComercial::where('client_id', $client->id)->firstOrFail();
        $this->assertSame($tenant->id, $proposta->tenant_id);
        $this->assertSame(PropostaComercial::STATUS_RASCUNHO, $proposta->status);
        $this->assertSame(1, $proposta->items()->count());
        $this->assertSame('3000.00', $proposta->items()->first()->unit_price);
    }

    public function test_botao_nova_proposta_aparece_na_listagem(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $this->actingAs($admin);

        Livewire::test(ListPropostaComerciais::class)
            ->assertActionExists('create');
    }
}
