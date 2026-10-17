<?php

namespace Tests\Feature;

use App\Console\Commands\ExpirarPropostasCrm;
use App\Filament\Resources\CrmLeadResource\Pages\ListCrmLeads;
use App\Filament\Resources\CrmPropostaResource;
use App\Models\CrmConta;
use App\Models\CrmLead;
use App\Models\CrmLeadItem;
use App\Models\CrmProduto;
use App\Models\CrmProposta;
use App\Models\CrmPropostaItem;
use App\Models\EmailMessage;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CrmFunilPadrao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Fase 6D-2: propostas próprias do CRM (PDF, envio, link público e aceite que fecha o lead). */
class CrmPropostasTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(?array $features = null): Tenant
    {
        $plan = Plan::create(['name' => 'Plano Prop '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => $features ?? ['tabela_crm_leads' => true, 'tabela_crm_contas' => true, 'tabela_crm_produtos' => true, 'tabela_crm_propostas' => true]]);

        return Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function admin(Tenant $t): User
    {
        $u = User::create(['name' => 'Admin '.uniqid(), 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    private function vendedor(Tenant $t): User
    {
        $u = User::create(['name' => 'Vendedor '.uniqid(), 'email' => 'v-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'comercial', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        foreach (['crm_lead', 'crm_proposta', 'crm_conta'] as $slug) {
            foreach (['ler', 'criar', 'editar'] as $acao) {
                Permission::firstOrCreate(['name' => "{$acao}_{$slug}", 'guard_name' => 'web']);
                $u->givePermissionTo("{$acao}_{$slug}");
            }
        }

        return $u;
    }

    private function proposta(Tenant $t, User $v, array $extra = []): CrmProposta
    {
        $p = CrmProposta::create($extra + ['tenant_id' => $t->id, 'vendedor_user_id' => $v->id, 'titulo' => 'Locação de geradores', 'validade_ate' => now()->addDays(10)]);
        $p->itens()->create(['tenant_id' => $t->id, 'descricao' => 'Gerador 100kVA', 'quantidade' => 2, 'preco_unitario' => 1500]);

        return $p->refresh();
    }

    private function enviada(Tenant $t, User $v, array $extra = []): CrmProposta
    {
        $p = $this->proposta($t, $v, $extra);
        $p->update(['status' => CrmProposta::STATUS_ENVIADA, 'enviada_em' => now()]);

        return $p->refresh();
    }

    public function test_numbering_is_per_tenant_and_total_follows_items_and_discount(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $v = $this->vendedor($a);

        $p1 = $this->proposta($a, $v);
        $p2 = $this->proposta($a, $v);
        $pb = $this->proposta($b, $this->vendedor($b));

        $this->assertSame([1, 2, 1], [$p1->numero, $p2->numero, $pb->numero]);
        $this->assertNotSame($p1->token, $p2->token);
        $this->assertGreaterThanOrEqual(48, strlen($p1->token));

        $this->assertEquals(3000.0, $p1->total);
        $p1->update(['desconto' => 500]);
        $this->assertEquals(2500.0, $p1->fresh()->total, 'desconto reflete no total');

        $extra = $p1->itens()->create(['tenant_id' => $a->id, 'descricao' => 'Frete', 'quantidade' => 1, 'preco_unitario' => 200]);
        $this->assertEquals(2700.0, $p1->fresh()->total);
        $extra->delete();
        $this->assertEquals(2500.0, $p1->fresh()->total);

        $p1->update(['desconto' => 99999]);
        $this->assertEquals(0.0, $p1->fresh()->total, 'nunca negativo');
    }

    public function test_proposal_from_lead_copies_account_and_interest_items(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $conta = CrmConta::create(['tenant_id' => $t->id, 'nome' => 'Acme Ltda', 'email' => 'c@acme.com']);
        $produto = CrmProduto::create(['tenant_id' => $t->id, 'nome' => 'Consultoria', 'preco' => 800]);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Maria', 'company_name' => 'Acme', 'crm_conta_id' => $conta->id, 'assigned_user_id' => $v->id]);
        CrmLeadItem::create(['tenant_id' => $t->id, 'crm_lead_id' => $lead->id, 'crm_produto_id' => $produto->id, 'quantidade' => 3]);
        CrmLeadItem::create(['tenant_id' => $t->id, 'crm_lead_id' => $lead->id, 'descricao' => 'Frete', 'quantidade' => 1, 'preco_unitario' => 100]);

        $p = CrmProposta::doLead($lead->fresh(), $v);

        $this->assertSame($conta->id, $p->crm_conta_id);
        $this->assertSame($lead->id, $p->crm_lead_id);
        $this->assertCount(2, $p->itens);
        $this->assertEquals(2500.0, $p->total);
        $this->assertSame('Acme Ltda', $p->destinatarioNome());

        $copia = $p->duplicar($v);
        $this->assertSame($p->numero + 1, $copia->numero);
        $this->assertNotSame($p->token, $copia->token);
        $this->assertEquals(2500.0, $copia->total);
        $this->assertSame('rascunho', $copia->status);
    }

    public function test_pdf_is_generated_and_downloadable_only_by_who_can_see_it(): void
    {
        $t = $this->tenant();
        $dono = $this->vendedor($t);
        $outro = $this->vendedor($t);
        $admin = $this->admin($t);
        $p = $this->proposta($t, $dono);

        $this->assertStringStartsWith('%PDF', $p->gerarPdf());

        $this->actingAs($dono)->get(route('crm.propostas.pdf', $p))->assertOk();
        $this->actingAs($admin)->get(route('crm.propostas.pdf', $p))->assertOk();
        $this->actingAs($outro)->get(route('crm.propostas.pdf', $p))->assertNotFound();
    }

    public function test_email_sending_registers_message_with_pdf_and_marks_sent_with_validations(): void
    {
        Storage::fake('local');
        Mail::fake();
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $conta = CrmConta::create(['tenant_id' => $t->id, 'nome' => 'Acme', 'email' => 'cliente@acme.com', 'whatsapp' => '(19) 99999-0000']);

        $p = $this->proposta($t, $v, ['crm_conta_id' => $conta->id]);
        $email = $p->enviarPorEmail($v);

        $this->assertSame(['cliente@acme.com'], $email->to_external);
        $this->assertStringContainsString($p->urlPublica(), $email->body);
        $this->assertTrue($email->hasMedia('anexos'));
        $this->assertSame('enviada', $p->fresh()->status);
        $this->assertNotNull($p->fresh()->enviada_em);
        $this->assertSame(1, EmailMessage::where('related_id', $p->id)->count());

        $this->expectException(\RuntimeException::class);
        $this->proposta($t, $v, ['crm_conta_id' => CrmConta::create(['tenant_id' => $t->id, 'nome' => 'Sem e-mail'])->id])->enviarPorEmail($v);
    }

    public function test_sending_requires_items_and_valid_deadline_and_whatsapp_link_is_built(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $conta = CrmConta::create(['tenant_id' => $t->id, 'nome' => 'Acme', 'email' => 'a@a.com', 'whatsapp' => '(19) 99999-0000']);

        $vazia = CrmProposta::create(['tenant_id' => $t->id, 'vendedor_user_id' => $v->id, 'titulo' => 'Sem itens', 'crm_conta_id' => $conta->id]);
        try {
            $vazia->enviarPorEmail($v);
            $this->fail('exige itens');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ao menos um item', $e->getMessage());
        }

        $vencida = $this->proposta($t, $v, ['crm_conta_id' => $conta->id, 'validade_ate' => now()->subDay()]);
        try {
            $vencida->enviarPorEmail($v);
            $this->fail('validade passada');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('validade', $e->getMessage());
        }

        $ok = $this->proposta($t, $v, ['crm_conta_id' => $conta->id]);
        $link = $ok->linkWhatsapp();
        $this->assertStringStartsWith('https://wa.me/5519999990000?text=', $link);
        $this->assertStringContainsString(rawurlencode($ok->urlPublica()), $link);
        $this->assertNull($this->proposta($t, $v)->linkWhatsapp(), 'sem número não há link');
    }

    public function test_public_link_shows_marks_viewed_and_hides_drafts_and_bad_tokens(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $p = $this->enviada($t, $v);

        $this->get(route('crm.proposta.publica', $p->token))->assertOk()->assertSee('Gerador 100kVA')->assertSee('R$ 3.000,00');
        $this->assertNotNull($p->fresh()->visualizada_em);

        $this->get(route('crm.proposta.publica', 'token-que-nao-existe'))->assertNotFound();
        $this->get(route('crm.proposta.publica', $this->proposta($t, $v)->token))->assertNotFound();   // rascunho não é público
    }

    public function test_accepting_online_closes_the_lead_as_won_creates_the_account_and_logs_history(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        CrmFunilPadrao::garantir($t->id);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Maria', 'company_name' => 'Acme Ltda', 'document' => '11.222.333/0001-81', 'estimated_value' => 100, 'assigned_user_id' => $v->id, 'stage' => 'qualificado']);
        $p = $this->enviada($t, $v, ['crm_lead_id' => $lead->id]);

        $this->post(route('crm.proposta.aceitar', $p->token), ['nome' => 'João da Silva'], ['REMOTE_ADDR' => '10.1.1.1'])->assertRedirect();

        $p->refresh();
        $this->assertSame('aceita', $p->status);
        $this->assertSame('João da Silva', $p->nome_aceite);
        $this->assertNotNull($p->ip_aceite);
        $this->assertNotNull($p->respondida_em);

        $lead->refresh();
        $this->assertTrue($lead->isWon());
        $this->assertEquals(3000.0, $lead->valor_fechado, 'a venda vale o total da proposta');
        $this->assertNotNull($lead->crm_conta_id, 'o ganho cria a conta e termina no CRM');
        $this->assertStringContainsString('aceita por João da Silva', $lead->interactions()->first()->summary);

        // Não aceita de novo nem recusa depois de aceita.
        $this->post(route('crm.proposta.aceitar', $p->token), ['nome' => 'Outro Nome'])->assertSessionHas('erro');
        $this->post(route('crm.proposta.recusar', $p->token), ['motivo' => 'mudei de ideia'])->assertSessionHas('erro');
        $this->assertSame('João da Silva', $p->fresh()->nome_aceite);
    }

    public function test_refusal_records_reason_and_keeps_the_lead_open(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        CrmFunilPadrao::garantir($t->id);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Maria', 'assigned_user_id' => $v->id, 'stage' => 'qualificado']);
        $p = $this->enviada($t, $v, ['crm_lead_id' => $lead->id]);

        $this->post(route('crm.proposta.recusar', $p->token), ['motivo' => 'Preço acima do orçamento'])->assertRedirect();

        $this->assertSame('recusada', $p->fresh()->status);
        $this->assertSame('Preço acima do orçamento', $p->fresh()->motivo_recusa);
        $this->assertTrue($lead->fresh()->isOpen(), 'recusa não marca o lead como perdido');
        $this->assertStringContainsString('recusada', $lead->interactions()->first()->summary);
    }

    public function test_public_validation_expired_proposals_and_the_expiry_command(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $p = $this->enviada($t, $v);

        $this->post(route('crm.proposta.aceitar', $p->token), ['nome' => 'ab'])->assertSessionHasErrors('nome');
        $this->post(route('crm.proposta.recusar', $p->token), [])->assertSessionHasErrors('motivo');
        $this->assertSame('enviada', $p->fresh()->status);

        $vencida = $this->enviada($t, $v);
        $vencida->update(['validade_ate' => now()->subDays(2)]);
        $this->post(route('crm.proposta.aceitar', $vencida->token), ['nome' => 'Fulano de Tal'])->assertSessionHas('erro');
        $this->assertSame('expirada', $vencida->fresh()->status);

        $naoMarcada = $this->enviada($t, $v);
        $naoMarcada->update(['validade_ate' => now()->subDays(3)]);
        $ainda = $this->enviada($t, $v);
        $rascunhoVencido = $this->proposta($t, $v, ['validade_ate' => now()->subDays(5)]);

        $this->artisan('crm:expirar-propostas')->assertSuccessful();

        $this->assertSame('expirada', $naoMarcada->fresh()->status);
        $this->assertSame('enviada', $ainda->fresh()->status);
        $this->assertSame('rascunho', $rascunhoVencido->fresh()->status, 'só enviadas expiram');
        $this->assertTrue(class_exists(ExpirarPropostasCrm::class));
    }

    public function test_public_link_is_rate_limited_per_ip(): void
    {
        $t = $this->tenant();
        $p = $this->enviada($t, $this->vendedor($t));

        for ($i = 0; $i < 30; $i++) {
            $this->get(route('crm.proposta.publica', $p->token))->assertOk();
        }

        $this->get(route('crm.proposta.publica', $p->token))->assertStatus(429);
    }

    public function test_manual_acceptance_reopen_and_duplicate_flows_in_the_resource(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        CrmFunilPadrao::garantir($t->id);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Maria', 'assigned_user_id' => $v->id, 'stage' => 'qualificado']);
        $p = $this->proposta($t, $v, ['crm_lead_id' => $lead->id]);
        $this->actingAs($v);

        Livewire::test(CrmPropostaResource\Pages\ListCrmPropostas::class)
            ->callTableAction('aceitarManual', $p, data: ['nome' => 'Cliente por telefone'])
            ->callTableAction('duplicar', $p);

        $this->assertSame('aceita', $p->fresh()->status);
        $this->assertTrue($lead->fresh()->isWon());
        $this->assertSame(2, CrmProposta::where('tenant_id', $t->id)->count(), 'cópia criada');

        $recusada = $this->enviada($t, $v);
        $recusada->recusar('não quero');
        Livewire::test(CrmPropostaResource\Pages\ListCrmPropostas::class)->callTableAction('reabrir', $recusada);
        $this->assertSame('rascunho', $recusada->fresh()->status);
        $this->assertNull($recusada->fresh()->motivo_recusa);

        $this->expectException(\RuntimeException::class);
        $this->proposta($t, $v)->reabrir();   // rascunho não reabre
    }

    public function test_resource_scopes_to_owner_and_tenant_renders_screens_and_gates_the_plan(): void
    {
        $t = $this->tenant();
        $outro = $this->tenant();
        $a = $this->vendedor($t);
        $b = $this->vendedor($t);
        $admin = $this->admin($t);
        $pa = $this->proposta($t, $a, ['titulo' => 'Da vendedora A']);
        $this->proposta($t, $b, ['titulo' => 'Do vendedor B']);
        $this->proposta($outro, $this->vendedor($outro), ['titulo' => 'De outra empresa']);

        $this->actingAs($a);
        $this->assertSame(1, CrmPropostaResource::getEloquentQuery()->count());
        Livewire::test(CrmPropostaResource\Pages\ListCrmPropostas::class)->assertSuccessful()->assertSee('Da vendedora A')->assertDontSee('Do vendedor B');
        Livewire::test(CrmPropostaResource\Pages\EditCrmProposta::class, ['record' => $pa->getKey()])->assertSuccessful();
        Livewire::test(CrmPropostaResource\RelationManagers\ItensRelationManager::class, ['ownerRecord' => $pa, 'pageClass' => CrmPropostaResource\Pages\EditCrmProposta::class])->assertSuccessful()->assertSee('Gerador 100kVA');
        Livewire::test(CrmPropostaResource\Widgets\CrmPropostaStats::class)->assertSuccessful();

        $this->actingAs($admin);
        $this->assertSame(2, CrmPropostaResource::getEloquentQuery()->count(), 'admin vê as da empresa, nunca de outra');

        $semModulo = $this->tenant(['tabela_crm_leads' => true]);
        $this->actingAs($this->admin($semModulo));
        $this->assertFalse(CrmPropostaResource::canViewAny());
    }

    public function test_lead_action_creates_a_proposal_prefilled_and_migration_is_reversible(): void
    {
        $t = $this->tenant();
        $v = $this->vendedor($t);
        $lead = CrmLead::create(['tenant_id' => $t->id, 'name' => 'Maria', 'company_name' => 'Acme', 'assigned_user_id' => $v->id]);
        CrmLeadItem::create(['tenant_id' => $t->id, 'crm_lead_id' => $lead->id, 'descricao' => 'Item X', 'quantidade' => 2, 'preco_unitario' => 50]);
        $this->actingAs($v);

        Livewire::test(ListCrmLeads::class)->callTableAction('nova_proposta', $lead);
        $p = CrmProposta::where('crm_lead_id', $lead->id)->firstOrFail();
        $this->assertEquals(100.0, $p->total);

        $migration = require base_path('database/migrations/2026_11_21_100000_create_crm_propostas.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('crm_propostas'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('crm_proposta_itens'));
        $this->assertNotNull(CrmPropostaItem::class);
    }
}
