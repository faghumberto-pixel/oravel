<?php

namespace Tests\Feature;

use App\Filament\Resources\ContractResource\Pages\ListContracts;
use App\Filament\Resources\QuoteResource\Pages\EditQuote;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\DocumentSignature;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\LinkWhatsapp;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Enviar por WhatsApp: orçamento (link público de aprovação) e contrato (link de assinatura), sempre com o link seguro, nunca anexo. */
class DocumentosWhatsappTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_quotes' => true, 'tabela_contracts' => true, 'tabela_clients' => true, 'tabela_assets' => true]]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'wz-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function cliente(Tenant $t, ?string $whatsapp = '(19) 98888-7766'): Client
    {
        return Client::create(['tenant_id' => $t->id, 'name' => 'Construtora Gama', 'whatsapp' => $whatsapp]);
    }

    private function orcamento(Tenant $t, Client $c, array $extra = []): Quote
    {
        $q = Quote::create($extra + ['tenant_id' => $t->id, 'client_id' => $c->id, 'status' => Quote::STATUS_RASCUNHO, 'total_value' => 0]);
        $q->items()->create(['tenant_id' => $t->id, 'type' => 'servico', 'description' => 'Mão de obra', 'quantity' => 1, 'unit_price' => 500, 'subtotal' => 500]);

        return $q->refresh();
    }

    private function contrato(Tenant $t, Client $c): Contract
    {
        $a = Asset::create(['tenant_id' => $t->id, 'name' => 'Gerador', 'status' => 'disponivel']);

        return Contract::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'asset_id' => $a->id, 'contract_number' => 'CT-9', 'start_date' => now(), 'billing_type' => 'fixo', 'price' => 1000]);
    }

    public function test_normaliza_o_numero_para_o_padrao_do_whatsapp(): void
    {
        $this->assertSame('5519988887766', LinkWhatsapp::numero('(19) 98888-7766'));
        $this->assertSame('551933334444', LinkWhatsapp::numero('19 3333-4444'));
        $this->assertSame('5519988887766', LinkWhatsapp::numero('+55 19 98888-7766'), 'já com DDI não duplica');
        $this->assertNull(LinkWhatsapp::numero('3333-4444'), 'sem DDD não serve');
        $this->assertNull(LinkWhatsapp::numero(null));
        $this->assertStringStartsWith('https://wa.me/5519988887766?text=', LinkWhatsapp::para('19988887766', 'Olá & tudo bem?'));
        $this->assertStringContainsString(rawurlencode('Olá & tudo bem?'), LinkWhatsapp::para('19988887766', 'Olá & tudo bem?'));
    }

    public function test_orcamento_so_tem_link_depois_de_emitido_e_com_telefone(): void
    {
        [$t] = $this->empresa();
        $q = $this->orcamento($t, $this->cliente($t));
        $this->assertNull($q->linkWhatsapp(), 'rascunho não tem link público ainda');

        $q->send();
        $link = $q->fresh()->linkWhatsapp();

        $this->assertStringStartsWith('https://wa.me/5519988887766?text=', $link);
        $this->assertStringContainsString(rawurlencode(route('quotes.public-approval', $q->fresh()->approval_token)), $link, 'leva o link público de aprovação');
        $this->assertStringContainsString(rawurlencode('Construtora Gama'), $link);

        $semTelefone = $this->orcamento($t, $this->cliente($t, null));
        $semTelefone->send();
        $this->assertNull($semTelefone->fresh()->linkWhatsapp());
    }

    public function test_acao_de_rascunho_emite_o_orcamento_e_avisa_com_o_botao(): void
    {
        [$t, $admin] = $this->empresa();
        $this->actingAs($admin);
        $q = $this->orcamento($t, $this->cliente($t));

        Livewire::test(EditQuote::class, ['record' => $q->getKey()])
            ->assertActionVisible('enviar_whatsapp')
            ->assertActionHidden('whatsapp')
            ->callAction('enviar_whatsapp')
            ->assertNotified('Orçamento emitido');

        $q->refresh();
        $this->assertSame(Quote::STATUS_ENVIADO, $q->status);
        $this->assertNotEmpty($q->approval_token);

        Livewire::test(EditQuote::class, ['record' => $q->getKey()])
            ->assertActionHidden('enviar_whatsapp')
            ->assertActionVisible('whatsapp');
    }

    public function test_sem_telefone_nao_oferece_o_envio_por_whatsapp(): void
    {
        [$t, $admin] = $this->empresa();
        $this->actingAs($admin);
        $q = $this->orcamento($t, $this->cliente($t, null));

        Livewire::test(EditQuote::class, ['record' => $q->getKey()])->assertActionHidden('enviar_whatsapp');
    }

    public function test_contrato_manda_o_link_de_assinatura_reaproveita_o_pendente_e_some_depois_de_assinado(): void
    {
        [$t, $admin] = $this->empresa();
        $this->actingAs($admin);
        $c = $this->contrato($t, $this->cliente($t));

        Livewire::test(ListContracts::class)->callTableAction('enviar_whatsapp', $c)->assertNotified('Link do contrato pronto');
        $this->assertSame(1, $c->signatures()->count(), 'gerou um link de assinatura');

        Livewire::test(ListContracts::class)->callTableAction('enviar_whatsapp', $c);
        $this->assertSame(1, $c->signatures()->count(), 'o segundo envio reaproveita o link pendente');

        $c->signatures()->update(['status' => 'signed', 'signed_at' => now()]);
        Livewire::test(ListContracts::class)->assertTableActionHidden('enviar_whatsapp', $c);
    }

    public function test_contrato_sem_telefone_do_cliente_nao_oferece_whatsapp(): void
    {
        [$t, $admin] = $this->empresa();
        $this->actingAs($admin);
        $c = $this->contrato($t, $this->cliente($t, null));

        Livewire::test(ListContracts::class)->assertTableActionHidden('enviar_whatsapp', $c);
    }

    public function test_assinatura_do_contrato_so_gera_link_da_propria_empresa(): void
    {
        [$a] = $this->empresa();
        [$b, $adminB] = $this->empresa();
        $this->actingAs($adminB);
        $contratoDaA = $this->contrato($a, $this->cliente($a));

        Livewire::test(ListContracts::class)->assertCanNotSeeTableRecords([$contratoDaA]);
        $this->assertSame(0, DocumentSignature::withoutGlobalScopes()->where('tenant_id', $b->id)->count());
    }
}
