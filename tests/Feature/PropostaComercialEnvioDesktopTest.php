<?php

namespace Tests\Feature;

use App\Filament\Pages\PropostaComercialKanban;
use App\Filament\Resources\PropostaComercialResource\Pages\EditPropostaComercial;
use App\Filament\Resources\PropostaComercialResource\Pages\ListPropostaComerciais;
use App\Filament\Resources\PropostaComercialResource\Pages\ViewPropostaComercial;
use App\Mail\GenericPdfMail;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\EmailMessage;
use App\Models\EquipmentDamage;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Enviar a proposta ao Comercial pelo computador (antes só o app de celular fazia isso). */
class PropostaComercialEnvioDesktopTest extends TestCase
{
    use DatabaseTransactions;

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial', 'tabela_solicitacao_locacao']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function rascunho(Tenant $tenant, User $vendedor, bool $comCliente = true, bool $comItem = true): PropostaComercial
    {
        $cliente = $comCliente ? Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente '.uniqid(), 'email' => uniqid().'@cliente.test']) : null;
        $proposta = PropostaComercial::create(['tenant_id' => $tenant->id, 'client_id' => $cliente?->id, 'seller_user_id' => $vendedor->id]);
        if ($comItem) {
            $categoria = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Gerador '.uniqid()]);
            $proposta->items()->create(['tenant_id' => $tenant->id, 'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $categoria->id,
                'description' => 'Gerador 180 kVA', 'quantity' => 1, 'unit_price' => 5000]);
        }

        return $proposta;
    }

    public function test_rascunho_e_enviado_ao_comercial_pela_tela_de_detalhe_e_avisa_o_comercial(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->cliente();
        $comercial = User::create(['name' => 'Comercial', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $comercial->assignRole(Role::firstOrCreate(['name' => EquipmentDamage::ROLE_COMERCIAL, 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $proposta = $this->rascunho($tenant, $admin);
        $this->actingAs($admin);

        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getRouteKey()])
            ->assertActionVisible('enviar_comercial')
            ->callAction('enviar_comercial', ['destinatario' => $comercial->id])
            ->assertNotified('Proposta enviada para revisão');

        $proposta->refresh();
        $this->assertSame(PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, $proposta->status);
        $this->assertNotNull($proposta->sent_at);
        $this->assertSame($comercial->id, $proposta->enviada_para_user_id);
        Mail::assertSent(GenericPdfMail::class, fn ($m) => $m->hasTo($comercial->email));

        // Depois de enviada, o botão e a edição somem.
        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getRouteKey()])->assertActionHidden('enviar_comercial')->assertActionHidden('editar_rascunho');
    }

    public function test_sem_cliente_ou_sem_item_o_envio_e_recusado_com_aviso_e_continua_rascunho(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);

        $semCliente = $this->rascunho($tenant, $admin, comCliente: false);
        Livewire::test(ViewPropostaComercial::class, ['record' => $semCliente->getRouteKey()])->callAction('enviar_comercial', ['destinatario' => $admin->id])->assertNotified('Não foi possível enviar');
        $semItem = $this->rascunho($tenant, $admin, comItem: false);
        Livewire::test(ViewPropostaComercial::class, ['record' => $semItem->getRouteKey()])->callAction('enviar_comercial', ['destinatario' => $admin->id])->assertNotified('Não foi possível enviar');

        $this->assertSame(PropostaComercial::STATUS_RASCUNHO, $semCliente->fresh()->status);
        $this->assertSame(PropostaComercial::STATUS_RASCUNHO, $semItem->fresh()->status);
    }

    public function test_a_lista_tem_o_botao_so_para_rascunho(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $rascunho = $this->rascunho($tenant, $admin);
        $enviada = $this->rascunho($tenant, $admin);
        $enviada->enviarParaComercial();

        Livewire::test(ListPropostaComerciais::class)
            ->assertTableActionVisible('enviar_comercial', $rascunho)
            ->assertTableActionHidden('enviar_comercial', $enviada)
            ->callTableAction('enviar_comercial', $rascunho, ['destinatario' => $admin->id]);

        $this->assertSame(PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, $rascunho->fresh()->status);
    }

    public function test_rascunho_pode_ser_editado_e_enviado_pelo_computador_e_enviada_fica_travada(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $proposta = $this->rascunho($tenant, $admin, comCliente: false);
        $cliente = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Novo', 'email' => 'novo@cliente.test']);

        Livewire::test(EditPropostaComercial::class, ['record' => $proposta->getRouteKey()])
            ->fillForm(['client_id' => $cliente->id])
            ->callAction('salvar_e_enviar', ['destinatario' => $admin->id])
            ->assertHasNoFormErrors();

        $proposta->refresh();
        $this->assertSame($cliente->id, $proposta->client_id);
        $this->assertSame(PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, $proposta->status);

        Livewire::test(EditPropostaComercial::class, ['record' => $proposta->getRouteKey()])->assertForbidden();
    }

    public function test_quem_nao_pode_editar_nao_envia_e_o_envio_e_do_proprio_cliente(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->cliente();
        [, $outroAdmin] = $this->cliente();
        $proposta = $this->rascunho($tenant, $admin);

        $this->actingAs($outroAdmin);
        $this->assertSame(0, PropostaComercial::count());   // proposta de outro cliente nem é enxergada
        try {
            Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getRouteKey()]);
            $this->fail('A proposta de outro cliente não deveria ser encontrada.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        $this->assertSame(PropostaComercial::STATUS_RASCUNHO, PropostaComercial::withoutGlobalScopes()->find($proposta->id)->status);
    }

    public function test_o_kanban_do_comercial_acompanha_a_proposta_depois_do_envio_pelo_computador(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $proposta = $this->rascunho($tenant, $admin);
        $kanban = fn () => (new PropostaComercialKanban)->getRecords();

        $this->assertTrue($kanban()->get(PropostaComercial::STATUS_RASCUNHO)->contains('id', $proposta->id));

        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getRouteKey()])->callAction('enviar_comercial', ['destinatario' => $admin->id]);

        $this->assertNull($kanban()->get(PropostaComercial::STATUS_RASCUNHO));
        $this->assertTrue($kanban()->get(PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL)->contains('id', $proposta->id));
    }

    private function aprovada(Tenant $tenant, User $admin, array $cliente = []): PropostaComercial
    {
        $proposta = $this->rascunho($tenant, $admin);
        $proposta->client->update(array_merge(['email' => 'cliente@teste.com', 'name' => 'Cliente Exemplo'], $cliente));
        $proposta->enviarParaComercial();
        $proposta->aprovar($admin);

        return $proposta->fresh();
    }

    public function test_enviar_pelo_cartao_do_kanban_move_o_cartao_e_recusa_quem_nao_pode(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $proposta = $this->rascunho($tenant, $admin);

        Livewire::test(PropostaComercialKanban::class)
            ->assertSee('Enviar para revisão')
            ->call('enviar', $proposta->id)
            ->assertNotified('Proposta enviada para revisão')
            ->assertDontSee('wire:click="enviar(');

        $this->assertSame(PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, $proposta->fresh()->status);

        $semItem = $this->rascunho($tenant, $admin, comItem: false);
        Livewire::test(PropostaComercialKanban::class)->call('enviar', $semItem->id)->assertNotified('Não foi possível enviar');
        $this->assertSame(PropostaComercial::STATUS_RASCUNHO, $semItem->fresh()->status);

        [, $outroAdmin] = $this->cliente();
        $this->actingAs($outroAdmin);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(PropostaComercialKanban::class)->call('enviar', $semItem->id);
    }

    public function test_reenviar_ao_cliente_manda_de_novo_o_pdf_pela_caixa_de_email_so_quando_aguarda_o_cliente(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $proposta = $this->aprovada($tenant, $admin);
        $emails = fn () => EmailMessage::where('related_type', PropostaComercial::class)->where('related_id', $proposta->id)->count();
        $this->assertSame(1, $emails());   // o da aprovação

        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getRouteKey()])
            ->assertActionVisible('reenviar_cliente')
            ->callAction('reenviar_cliente')
            ->assertNotified('Proposta reenviada ao cliente');
        $this->assertSame(2, $emails());

        // Sem e-mail do cliente ou fora do estado "aguardando cliente": recusa.
        $proposta->client->update(['email' => null]);
        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getRouteKey()])->callAction('reenviar_cliente')->assertNotified('Não foi possível reenviar');
        $this->assertSame(2, $emails());
        $rascunho = $this->rascunho($tenant, $admin);
        Livewire::test(ViewPropostaComercial::class, ['record' => $rascunho->getRouteKey()])->assertActionHidden('reenviar_cliente')->assertActionHidden('whatsapp_cliente');
    }

    public function test_link_do_whatsapp_leva_o_numero_a_mensagem_e_o_link_de_aceite(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $proposta = $this->aprovada($tenant, $admin, ['whatsapp' => '(19) 99933-2615']);

        $link = $proposta->linkWhatsapp();
        $this->assertStringStartsWith('https://wa.me/5519999332615?text=', $link);
        $texto = urldecode(substr($link, strlen('https://wa.me/5519999332615?text=')));
        $this->assertStringContainsString('Cliente Exemplo', $texto);
        $this->assertStringContainsString(route('proposta-comercial.public-approval', $proposta->approval_token), $texto);

        // Usa o telefone quando não há WhatsApp; número já com 55 não é duplicado; número curto não gera link.
        $proposta->client->update(['whatsapp' => null, 'phone' => '5511988887777']);
        $this->assertStringStartsWith('https://wa.me/5511988887777?text=', $proposta->fresh()->linkWhatsapp());
        $proposta->client->update(['phone' => '1234']);
        $this->assertNull($proposta->fresh()->linkWhatsapp());

        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getRouteKey()])->assertActionHidden('whatsapp_cliente');
        $proposta->client->update(['whatsapp' => '19999332615']);
        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getRouteKey()])->assertActionVisible('whatsapp_cliente');
        Livewire::test(PropostaComercialKanban::class)->assertSee('Enviar por WhatsApp');
    }

    public function test_proposta_aprovada_vai_pelo_whatsapp_do_sistema_com_o_numero_de_quem_envia(): void
    {
        \Illuminate\Support\Facades\Http::fake(['graph.facebook.com/*' => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => 'wamid.P1']]], 200)]);
        Mail::fake();
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $proposta = $this->aprovada($tenant, $admin);
        $proposta->client->update(['whatsapp' => '(19) 99933-2615']);

        // sem WhatsApp ligado: o botão do sistema não aparece (só o link manual)
        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getRouteKey()])->assertActionHidden('whatsapp_sistema')->assertActionVisible('whatsapp_cliente');
        $this->assertNull($proposta->fresh()->enviarPorWhatsApp($admin));

        \App\Models\TenantWhatsappSetting::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'enabled' => true, 'access_token' => 'TOK', 'app_secret' => 'S', 'verify_token' => 'V', 'template_proposta' => 'proposta_enviada']);
        \App\Models\WhatsappNumero::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'user_id' => $admin->id, 'phone_number_id' => 'N-ADMIN']);

        Livewire::test(ViewPropostaComercial::class, ['record' => $proposta->getRouteKey()])
            ->assertActionVisible('whatsapp_sistema')
            ->callAction('whatsapp_sistema')
            ->assertNotified('Proposta enviada pelo WhatsApp');

        $link = route('proposta-comercial.public-approval', $proposta->fresh()->approval_token);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->url(), '/N-ADMIN/messages')
            && $r['to'] === '5519999332615' && $r['template']['name'] === 'proposta_enviada'
            && $r['template']['components'][0]['parameters'][1]['text'] === $link);

        $mensagem = \App\Models\WhatsappMensagem::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('enviada', $mensagem->status);
        $this->assertSame($proposta->id, $mensagem->related_id);
        $this->assertSame($admin->id, $mensagem->enviada_por_user_id);
    }
}
