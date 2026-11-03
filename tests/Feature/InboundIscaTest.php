<?php

namespace Tests\Feature;

use App\Mail\PlanilhaManutencaoMail;
use App\Models\Plan;
use App\Models\SalesLead;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Isca do site (HostGator) -> POST /api/inbound/leads: o app grava o lead e ENVIA o material por e-mail
 * (o site não tem SMTP próprio) e responde `email_enviado` para o site decidir o que mostrar ao visitante.
 */
class InboundIscaTest extends TestCase
{
    use DatabaseTransactions;

    private const TOKEN = 'token-de-teste-isca';

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create(['name' => 'Plano Isca '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $tenant = Tenant::create(['name' => 'Oravel Isca', 'slug' => 'oravel-isca-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);

        config([
            'cache.default' => 'array',
            'services.inbound.channels' => ['site-oravel' => ['token' => self::TOKEN, 'tenant_slug' => $tenant->slug, 'destination' => 'central']],
        ]);
    }

    private function enviar(array $extra = [])
    {
        return $this->postJson('/api/inbound/leads', array_merge([
            'lead_id' => bin2hex(random_bytes(6)),
            'origem' => 'isca-planilha-manutencao',
            'name' => 'Maria Souza',
            'company' => 'Locadora Isca',
            'email' => 'maria.isca@example.com',
            'phone' => '(19) 99999-1234',
            'segmento' => 'Não informado',
            'porte' => 'Não informado',
        ], $extra), ['X-Channel-Token' => self::TOKEN]);
    }

    public function test_origem_de_isca_grava_o_lead_envia_a_planilha_e_avisa_o_site(): void
    {
        Mail::fake();

        $this->enviar()->assertCreated()->assertJson(['success' => true, 'email_enviado' => true]);

        $lead = SalesLead::where('email', 'maria.isca@example.com')->firstOrFail();
        $this->assertSame('isca-planilha-manutencao', $lead->inbound_details['origem']);

        Mail::assertSent(PlanilhaManutencaoMail::class, fn (PlanilhaManutencaoMail $m) => $m->hasTo('maria.isca@example.com') && $m->nome === 'Maria Souza' && count($m->attachments()) === 1);
    }

    public function test_falha_no_smtp_nao_perde_o_lead_e_avisa_o_site_que_nao_enviou(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP Error: Could not authenticate.'));

        $this->enviar()->assertCreated()->assertJson(['success' => true, 'email_enviado' => false]);

        $this->assertSame(1, SalesLead::where('email', 'maria.isca@example.com')->count());
    }

    public function test_origem_que_nao_e_isca_nao_envia_e_nao_traz_email_enviado(): void
    {
        Mail::fake();

        $r = $this->enviar(['origem' => 'home'])->assertCreated();

        $this->assertArrayNotHasKey('email_enviado', $r->json());
        Mail::assertNothingSent();
    }
}
