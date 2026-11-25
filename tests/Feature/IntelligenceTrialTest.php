<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Models\IntelligenceImport;
use App\Domain\Intelligence\Services\ImportadorExcel;
use App\Domain\Intelligence\Support\Catalogo;
use App\Domain\Intelligence\Trial\Enums\StatusConta;
use App\Domain\Intelligence\Trial\Mail\ContratoAceitoMail;
use App\Domain\Intelligence\Trial\Mail\NovoCadastroTrialMail;
use App\Domain\Intelligence\Trial\Mail\TrialMail;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Models\IntelligenceWebhookEvento;
use App\Domain\Intelligence\Trial\Services\AssinaturaPlano;
use App\Domain\Intelligence\Trial\Services\TrialLembretes;
use App\Domain\Intelligence\Trial\Support\TelefoneBr;
use App\Filament\Resources\IntelligenceImportResource;
use App\Models\SalesLead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class IntelligenceTrialTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.asaas.api_key' => 'chave-de-teste', 'services.asaas.base_url' => 'https://asaas.test/v3', 'services.asaas.webhook_token' => 'tok',
            'intelligence_trial.notificar_email' => 'dono@example.test',
        ]);
        Mail::fake();
        Storage::fake('local');
    }

    private function cadastrar(array $over = [])
    {
        return $this->post('/intelligence/teste-gratis', array_merge([
            'nome' => 'Maria Silva', 'empresa' => 'Locadora Teste', 'whatsapp' => '(19) 99999-1234', 'aceite' => '1',
        ], $over));
    }

    private function trial(): IntelligenceTrial
    {
        $this->cadastrar();

        return IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', '+5519999991234')->firstOrFail();
    }

    private function planilha(int $frota = 100): string
    {
        $linha = ['Data de Referência' => '30/09/2026', 'Porte' => 'médio', 'Total de Equipamentos' => $frota, 'Equipamentos Locados' => 80,
            'Equipamentos Indisponíveis' => 15, 'Valor da Frota (R$)' => 9000000, 'Receita do Mês (R$)' => '450.000,00',
            'Custo de Manutenção do Mês (R$)' => 45000, 'Demais Custos do Mês (R$)' => 200000, 'OS Corretivas no Mês' => 30, 'OS Preventivas no Mês' => 10];
        $path = tempnam(sys_get_temp_dir(), 'intel').'.xlsx';
        $titulos = array_column(Catalogo::campos(), 'title');
        $w = new Writer;
        $w->openToFile($path);
        $w->getCurrentSheet()->setName(Catalogo::SHEET);
        $w->addRow(Row::fromValues(array_column(Catalogo::campos(), 'group')));
        $w->addRow(Row::fromValues($titulos));
        $w->addRow(Row::fromValues(array_map(fn ($t) => $linha[$t] ?? null, $titulos)));
        $w->close();

        return $path;
    }

    /** E-mails de lembrete (exclui o aviso ao dono e o contrato, que também herdam de TrialMail). */
    private function lembretesEnviados(): int
    {
        return Mail::sent(TrialMail::class)->filter(fn ($m) => ! $m instanceof NovoCadastroTrialMail && ! $m instanceof ContratoAceitoMail)->count();
    }

    private function dadosContrato(array $over = []): array
    {
        return array_merge(['razao_social' => 'Locadora Teste Ltda', 'cnpj' => '11.222.333/0001-81', 'email' => 'fin@locadora.test', 'responsavel' => 'Maria Silva', 'forma_pagamento' => 'UNDEFINED'], $over);
    }

    private function fakeAsaas(): void
    {
        Http::fake([
            'asaas.test/v3/customers?*' => Http::response(['data' => []]),
            'asaas.test/v3/customers' => Http::response(['id' => 'cus_1']),
            'asaas.test/v3/subscriptions' => Http::response(['id' => 'sub_1']),
            'asaas.test/v3/subscriptions/*' => Http::response(['id' => 'sub_1', 'nextDueDate' => now()->addDays(12)->toDateString()]),
        ]);
    }

    private function webhook(string $evento, array $extra = [], ?string $id = null)
    {
        return $this->postJson('/api/webhooks/asaas', array_filter(['id' => $id, 'event' => $evento] + $extra), ['asaas-access-token' => 'tok']);
    }

    // ---- cadastro ----

    public function test_normaliza_whatsapp_br(): void
    {
        $this->assertSame('+5519999991234', TelefoneBr::e164('(19) 99999-1234'));
        $this->assertSame('+5519999991234', TelefoneBr::e164('+55 19 99999-1234'));
        $this->assertNull(TelefoneBr::e164('1933334444'));   // fixo
        $this->assertNull(TelefoneBr::e164('123'));
    }

    public function test_cadastro_cria_conta_autentica_registra_lead_e_avisa_o_dono(): void
    {
        $leads = SalesLead::count();
        $r = $this->cadastrar();

        $trial = IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', '+5519999991234')->firstOrFail();
        $r->assertRedirect(IntelligenceImportResource::getUrl('index'));
        $this->assertAuthenticatedAs($trial->user);
        $this->assertTrue((bool) $trial->user->two_factor_exempt, 'sem senha/app autenticador: 2FA não pode barrar o cliente do teste');
        $this->assertSame('Locadora Teste', $trial->tenant->name);
        $this->assertNull($trial->trial_started_at, 'a contagem só começa no 1º envio');
        $this->assertSame($leads + 1, SalesLead::count());
        $this->assertSame('Intelligence – teste grátis', SalesLead::latest('created_at')->first()->inbound_details['origem']);
        Mail::assertSent(NovoCadastroTrialMail::class, fn ($m) => $m->hasTo('dono@example.test'));
        $r->assertSessionHas('intelligence_link');
    }

    public function test_cadastro_valida_whatsapp_aceite_e_honeypot(): void
    {
        $antes = IntelligenceTrial::withoutGlobalScopes()->count();
        $this->cadastrar(['whatsapp' => '123'])->assertSessionHasErrors('whatsapp');
        $this->cadastrar(['aceite' => null])->assertSessionHasErrors('aceite');
        $this->cadastrar(['site' => 'http://spam'])->assertSessionHasErrors('site');
        $this->assertSame($antes, IntelligenceTrial::withoutGlobalScopes()->count());
    }

    public function test_whatsapp_repetido_nao_cria_novo_teste_nem_reinicia_os_dias(): void
    {
        $trial = $this->trial();
        $trial->forceFill(['trial_started_at' => now()->subDays(5), 'trial_ends_at' => now()->addDays(25)])->save();
        $tenants = Tenant::withoutGlobalScopes()->count();
        auth()->logout();

        $this->cadastrar(['empresa' => 'Outra Empresa'])->assertRedirect();

        $this->assertSame($tenants, Tenant::withoutGlobalScopes()->count());
        $this->assertSame(1, IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', '+5519999991234')->count());
        $this->assertEqualsWithDelta(25, $trial->fresh()->diasRestantes(), 1);
    }

    public function test_link_pessoal_autentica_e_novo_link_invalida_o_anterior(): void
    {
        $trial = $this->trial();
        $token = $trial->novoTokenDeAcesso();
        auth()->logout();

        $this->get("/intelligence/entrar/{$token}")->assertRedirect();
        $this->assertAuthenticatedAs($trial->user);
        auth()->logout();

        $novo = $trial->novoTokenDeAcesso();
        $this->get("/intelligence/entrar/{$token}")->assertRedirect(route('intelligence.trial.novo-link'));
        $this->assertGuest();
        $this->get("/intelligence/entrar/{$novo}")->assertRedirect();
        $this->assertAuthenticatedAs($trial->user);
    }

    public function test_pedir_novo_link_pelo_whatsapp_mostra_o_link_quando_nao_ha_canal(): void
    {
        $this->trial();
        auth()->logout();

        $this->post('/intelligence/novo-link', ['whatsapp' => '19999991234'])->assertSessionHas('link');
        $this->post('/intelligence/novo-link', ['whatsapp' => '19988887777'])->assertSessionHasErrors('whatsapp');
    }

    // ---- contagem dos 30 dias e somente leitura ----

    public function test_contagem_comeca_no_primeiro_envio_com_sucesso_e_nao_reinicia(): void
    {
        $trial = $this->trial();
        $tenant = $trial->tenant;
        $imp = app(ImportadorExcel::class);

        $imp->importar($this->planilha(), $tenant, $trial->user, true, 'a.xlsx'); // simulação não conta
        $this->assertNull($trial->fresh()->trial_started_at);

        $this->assertTrue($imp->importar($this->planilha(), $tenant, $trial->user, false, 'a.xlsx')['ok']);
        $trial->refresh();
        $this->assertNotNull($trial->trial_started_at);
        $this->assertEquals($trial->trial_started_at->copy()->addDays(30)->toDateString(), $trial->trial_ends_at->toDateString());

        $inicio = $trial->trial_started_at;
        $this->travel(3)->days();
        $this->assertTrue($imp->importar($this->planilha(120), $tenant, $trial->user, false, 'b.xlsx')['ok']); // reenvio a qualquer momento
        $this->assertTrue($trial->fresh()->trial_started_at->equalTo($inicio));
        $this->assertSame(2, IntelligenceImport::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_inicio_pode_ser_desde_o_cadastro_por_config(): void
    {
        config(['intelligence_trial.inicio' => 'cadastro']);
        $this->assertNotNull($this->trial()->trial_started_at);
    }

    public function test_teste_encerrado_vira_somente_leitura_sem_apagar_nada(): void
    {
        $trial = $this->trial();
        $imp = app(ImportadorExcel::class);
        $imp->importar($this->planilha(), $trial->tenant, $trial->user, false, 'a.xlsx');

        $this->travel(31)->days();
        $trial->refresh();
        $this->assertSame(StatusConta::Expired, $trial->statusAtual());
        $this->assertTrue($trial->somenteLeitura());

        $r = $imp->importar($this->planilha(130), $trial->tenant, $trial->user, false, 'c.xlsx');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('teste grátis terminou', $r['erros'][0]);
        $this->assertSame(1, IntelligenceImport::withoutGlobalScopes()->where('tenant_id', $trial->tenant_id)->count(), 'histórico preservado, nada novo gravado');
    }

    public function test_status_trial_expiring_nos_ultimos_dias(): void
    {
        $trial = $this->trial();
        $trial->forceFill(['trial_started_at' => now()->subDays(22), 'trial_ends_at' => now()->addDays(8)])->save();
        $this->assertSame(StatusConta::TrialExpiring, $trial->statusAtual());
        $this->assertFalse($trial->somenteLeitura());
    }

    // ---- lembretes ----

    public function test_lembretes_nos_dias_20_27_e_30_sem_duplicar(): void
    {
        $trial = $this->trial();
        $trial->user->forceFill(['email' => 'cliente@empresa.test'])->save();
        $trial->forceFill(['trial_started_at' => now()->startOfDay(), 'trial_ends_at' => now()->startOfDay()->addDays(30)])->save();
        $svc = app(TrialLembretes::class);

        $this->assertSame(0, $svc->executar(now()->addDays(18))['enviados']);   // dia 19
        $this->assertSame(1, $svc->executar(now()->addDays(19))['enviados']);   // dia 20
        $this->assertSame(0, $svc->executar(now()->addDays(19))['enviados']);   // rodar de novo no mesmo dia
        $this->assertSame(1, $svc->executar(now()->addDays(26))['enviados']);   // dia 27
        $this->assertSame(1, $svc->executar(now()->addDays(29))['enviados']);   // dia 30
        $this->assertSame(0, $svc->executar(now()->addDays(29))['enviados']);

        $this->assertSame(3, $this->lembretesEnviados());
    }

    public function test_lembrete_sem_canal_fica_registrado_e_nao_repete(): void
    {
        $trial = $this->trial(); // e-mail ainda é o provisório (.invalid) e não há WhatsApp da Oravel
        $trial->forceFill(['trial_started_at' => now()->startOfDay(), 'trial_ends_at' => now()->startOfDay()->addDays(30)])->save();
        $svc = app(TrialLembretes::class);

        $this->assertSame(1, $svc->executar(now()->addDays(19))['sem_canal']);
        $this->assertSame(0, $svc->executar(now()->addDays(19))['sem_canal']);
        $this->assertSame(0, $this->lembretesEnviados());
    }

    public function test_quem_ja_contratou_nao_recebe_lembrete(): void
    {
        $trial = $this->trial();
        $trial->forceFill(['contracted_at' => now(), 'trial_started_at' => now()->startOfDay(), 'trial_ends_at' => now()->addDays(30)])->save();
        $this->assertSame(['enviados' => 0, 'sem_canal' => 0], app(TrialLembretes::class)->executar(now()->addDays(19)));
    }

    // ---- contratação ----

    public function test_contratar_durante_o_teste_cobra_so_depois_do_fim(): void
    {
        $this->fakeAsaas();
        $trial = $this->trial();
        $trial->forceFill(['trial_started_at' => now()->subDays(10), 'trial_ends_at' => now()->addDays(20)])->save();

        $c = app(AssinaturaPlano::class)->contratar($trial, $this->dadosContrato(), '203.0.113.9', 'UA-Teste');

        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/subscriptions') && $req['nextDueDate'] === $trial->trial_ends_at->toDateString()
            && $req['billingType'] === 'UNDEFINED' && (float) $req['value'] === 39.9 && $req['cycle'] === 'MONTHLY');
        $trial->refresh();
        $this->assertSame('sub_1', $trial->asaas_subscription_id);
        $this->assertSame('cus_1', $trial->asaas_customer_id);
        $this->assertSame(hash('sha256', $c->texto), $c->hash);
        $this->assertSame('203.0.113.9', $c->ip);
        $this->assertStringContainsString($trial->trial_ends_at->format('d/m/Y'), $c->texto);
        Storage::disk('local')->assertExists($c->fresh()->pdf_path);
        Mail::assertSent(ContratoAceitoMail::class, fn ($m) => $m->hasTo('fin@locadora.test'));
        $this->assertNotSame(StatusConta::Active, $trial->statusAtual(), 'só vira ativo na 1ª cobrança confirmada');
        $this->assertFalse($trial->somenteLeitura());
    }

    public function test_contratar_antes_do_primeiro_envio_cobra_so_apos_os_30_dias(): void
    {
        $this->fakeAsaas();
        $trial = $this->trial(); // trial_ends_at ainda nulo

        app(AssinaturaPlano::class)->contratar($trial, $this->dadosContrato(), null, null);

        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/subscriptions') && $req['nextDueDate'] === today()->addDays(30)->toDateString());
        $this->assertNotNull($trial->fresh()->trial_started_at, 'contratar começa o relógio do teste');
    }

    public function test_contratar_depois_do_fim_do_teste_cobra_hoje(): void
    {
        $this->fakeAsaas();
        $trial = $this->trial();
        $trial->forceFill(['trial_started_at' => now()->subDays(40), 'trial_ends_at' => now()->subDays(10)])->save();

        app(AssinaturaPlano::class)->contratar($trial, $this->dadosContrato(), null, null);

        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/subscriptions') && $req['nextDueDate'] === today()->toDateString());
        $this->assertFalse($trial->fresh()->somenteLeitura(), 'contratou: volta a poder enviar');
    }

    public function test_cliente_do_asaas_e_reaproveitado_pelo_cnpj(): void
    {
        Http::fake([
            'asaas.test/v3/customers?*' => Http::response(['data' => [['id' => 'cus_existente']]]),
            'asaas.test/v3/subscriptions' => Http::response(['id' => 'sub_9']),
        ]);
        $trial = $this->trial();

        app(AssinaturaPlano::class)->contratar($trial, $this->dadosContrato(), null, null);

        $this->assertSame('cus_existente', $trial->fresh()->asaas_customer_id);
        Http::assertNotSent(fn ($req) => $req->method() === 'POST' && str_ends_with($req->url(), '/customers'));
    }

    public function test_falha_no_asaas_nao_registra_contratacao(): void
    {
        Http::fake(['asaas.test/*' => Http::response(['errors' => []], 400)]);
        $trial = $this->trial();

        $this->expectException(\DomainException::class);
        try {
            app(AssinaturaPlano::class)->contratar($trial, $this->dadosContrato(), null, null);
        } finally {
            $this->assertNull($trial->fresh()->contracted_at);
            $this->assertSame(0, $trial->contratos()->count());
        }
    }

    public function test_formulario_de_contratacao_valida_e_exige_aceite(): void
    {
        $this->fakeAsaas();
        $trial = $this->trial();

        $this->post('/intelligence/contratar', $this->dadosContrato(['cnpj' => '123']))->assertSessionHasErrors('cnpj');
        $this->post('/intelligence/contratar', $this->dadosContrato())->assertSessionHasErrors('aceite_contrato');
        $this->post('/intelligence/contratar', $this->dadosContrato() + ['aceite_contrato' => '1'])->assertRedirect(route('intelligence.trial.plano'));
        $this->assertNotNull($trial->fresh()->contracted_at);
    }

    public function test_cancelar_mantem_acesso_ate_o_fim_do_periodo_pago(): void
    {
        $this->fakeAsaas();
        $trial = $this->trial();
        app(AssinaturaPlano::class)->contratar($trial, $this->dadosContrato(), null, null);
        $trial->forceFill(['billing_starts_on' => today()->subMonth(), 'trial_ends_at' => now()->subMonth(), 'status' => StatusConta::Active])->save();

        app(AssinaturaPlano::class)->cancelar($trial->fresh());

        Http::assertSent(fn ($req) => $req->method() === 'DELETE' && str_ends_with($req->url(), '/subscriptions/sub_1'));
        $trial->refresh();
        $this->assertSame(StatusConta::Canceled, $trial->statusAtual());
        $this->assertSame(now()->addDays(12)->toDateString(), $trial->access_until->toDateString());
        $this->assertFalse($trial->somenteLeitura());
        $this->travel(13)->days();
        $this->assertTrue($trial->fresh()->somenteLeitura());
    }

    // ---- webhook ----

    private function trialContratado(): IntelligenceTrial
    {
        $trial = $this->trial();
        $trial->forceFill(['asaas_customer_id' => 'cus_1', 'asaas_subscription_id' => 'sub_1', 'contracted_at' => now(), 'trial_started_at' => now()->subDays(31), 'trial_ends_at' => now()->subDay(), 'billing_starts_on' => today()->subDay()])->save();

        return $trial;
    }

    public function test_webhook_rejeita_token_invalido(): void
    {
        $this->postJson('/api/webhooks/asaas', ['event' => 'PAYMENT_CONFIRMED'], ['asaas-access-token' => 'errado'])->assertStatus(401);
    }

    public function test_webhook_pagamento_confirmado_ativa_e_e_idempotente(): void
    {
        $trial = $this->trialContratado();
        $payload = ['payment' => ['id' => 'pay_1', 'customer' => 'cus_1', 'subscription' => 'sub_1', 'status' => 'CONFIRMED']];

        $this->webhook('PAYMENT_CONFIRMED', $payload, 'evt_1')->assertOk();
        $this->webhook('PAYMENT_CONFIRMED', $payload, 'evt_1')->assertOk();

        $this->assertSame(StatusConta::Active, $trial->fresh()->statusAtual());
        $this->assertSame(1, IntelligenceWebhookEvento::where('chave', 'evt_1')->count());
    }

    public function test_webhook_atraso_tem_carencia_e_depois_somente_leitura_e_pagamento_regulariza(): void
    {
        $trial = $this->trialContratado();
        $payload = ['payment' => ['id' => 'pay_2', 'customer' => 'cus_1', 'subscription' => 'sub_1', 'status' => 'OVERDUE']];

        $this->webhook('PAYMENT_OVERDUE', $payload, 'evt_2');
        $trial->refresh();
        $this->assertSame(StatusConta::Overdue, $trial->statusAtual());
        $this->assertFalse($trial->somenteLeitura(), 'dentro da carência de 5 dias');

        $this->travel(6)->days();
        $this->assertTrue($trial->fresh()->somenteLeitura());
        $r = app(ImportadorExcel::class)->importar($this->planilha(), $trial->tenant, $trial->user, false, 'x.xlsx');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('cobrança em atraso', $r['erros'][0]);

        $this->webhook('PAYMENT_RECEIVED', ['payment' => ['id' => 'pay_2', 'customer' => 'cus_1', 'subscription' => 'sub_1', 'status' => 'RECEIVED']], 'evt_3');
        $this->assertFalse($trial->fresh()->somenteLeitura());
        $this->assertSame(StatusConta::Active, $trial->fresh()->statusAtual());
    }

    public function test_webhook_cancelamento_da_assinatura(): void
    {
        $trial = $this->trialContratado();

        $this->webhook('SUBSCRIPTION_DELETED', ['subscription' => ['id' => 'sub_1', 'customer' => 'cus_1', 'status' => 'INACTIVE']], 'evt_4')->assertOk();

        $this->assertSame(StatusConta::Canceled, $trial->fresh()->statusAtual());
    }

    public function test_pagamento_apagado_ou_criado_nao_muda_o_acesso(): void
    {
        $trial = $this->trialContratado();
        foreach (['PAYMENT_CREATED', 'PAYMENT_DELETED'] as $i => $ev) {
            $this->webhook($ev, ['payment' => ['id' => 'pay_x', 'customer' => 'cus_1', 'subscription' => 'sub_1', 'status' => 'PENDING']], "evt_{$i}x");
        }
        $this->assertFalse($trial->fresh()->somenteLeitura());
        $this->assertNull($trial->fresh()->canceled_at);
    }

    // ---- telas ----

    public function test_telas_publicas_e_do_plano_renderizam(): void
    {
        $this->get('/intelligence/teste-gratis')->assertOk()->assertSee('Enviar minha planilha')->assertSee('Termos de Uso');
        $this->get('/intelligence/novo-link')->assertOk();

        $trial = $this->trial();
        $this->get('/intelligence/contratar')->assertOk()->assertSee('Aceitar e contratar')->assertSee('39,90');
        $this->get('/intelligence/plano')->assertOk()->assertSee('Contratar plano básico');
        $this->assertAuthenticatedAs($trial->user);
    }

    public function test_banner_aparece_dentro_do_sistema_com_o_link_pessoal(): void
    {
        $this->cadastrar();
        $trial = IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', '+5519999991234')->firstOrFail();
        $trial->forceFill(['trial_started_at' => now(), 'trial_ends_at' => now()->addDays(30)])->save();

        // A tela inteira do painel (ChatWidget) usa SQL de Postgres e não roda neste ambiente de teste: renderiza só o banner.
        $html = view('filament.intelligence-trial-banner')->render();
        $this->assertStringContainsString('Teste grátis:', $html);
        $this->assertStringContainsString('Contratar plano básico', $html);
        $this->assertStringContainsString('30 dia(s) restantes', $html);
    }
}
