<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Trial\Mail\NovoCadastroTrialMail;
use App\Domain\Intelligence\Trial\Mail\TrialMail;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrialPendente;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Objetivo escolhido na etapa 1 da landing: vai do formulário ao lead no CRM e ao aviso ao dono. */
class IntelligenceCadastroObjetivoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['intelligence_trial.notificar_email' => 'dono@example.test', 'services.turnstile.secret' => null]);
    }

    private function cadastrar(array $over = [])
    {
        return $this->post('/intelligence/teste-gratis', array_merge([
            'nome' => 'Marta', 'empresa' => 'Locadora Marta', 'whatsapp' => '(19) 97777-6655', 'email' => 'marta@locadora.test', 'senha' => 'Senha1234', 'aceite' => '1', 'objetivo' => 'frota',
        ], $over));
    }

    public function test_objetivo_chega_ao_pendente_a_conta_o_lead_e_o_aviso_ao_dono(): void
    {
        $this->cadastrar()->assertRedirect(route('intelligence.trial.verifique'));
        $this->assertSame('frota', IntelligenceTrialPendente::first()->objetivo);

        $link = Mail::sent(TrialMail::class)->filter(fn ($m) => str_contains((string) $m->link, '/intelligence/confirmar/'))->last()->link;
        $this->post(parse_url($link, PHP_URL_PATH))->assertRedirect();

        $trial = IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', '+5519977776655')->firstOrFail();
        $this->assertSame('frota', $trial->objetivo);
        $this->assertSame('Tenho equipamentos parados ou improdutivos?', $trial->objetivoRotulo());

        $lead = SalesLead::where('company_name', 'Locadora Marta')->firstOrFail();
        $this->assertSame('Tenho equipamentos parados ou improdutivos?', $lead->inbound_details['objetivo']);

        Mail::assertSent(NovoCadastroTrialMail::class, fn ($m) => $m->hasTo('dono@example.test'));
    }

    public function test_cadastro_sem_objetivo_continua_valendo(): void
    {
        $this->cadastrar(['objetivo' => null])->assertRedirect(route('intelligence.trial.verifique'));
        $this->assertNull(IntelligenceTrialPendente::first()->objetivo);
    }

    public function test_objetivo_inventado_e_recusado(): void
    {
        $this->cadastrar(['objetivo' => 'qualquer-coisa'])->assertSessionHasErrors('objetivo');
        $this->assertSame(0, IntelligenceTrialPendente::count());
    }
}
