<?php

namespace Tests\Feature;

use App\Mail\MaterialGratuitoMail;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class IscaPlanilhaTest extends TestCase
{
    use DatabaseTransactions;

    private array $dados = [
        'name' => 'Maria Souza',
        'email' => 'maria.isca@example.com',
        'phone' => '(19) 99999-1234',
        'company' => 'Locadora Isca Teste',
        'aceite' => '1',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('isca');
    }

    public function test_pagina_publica_tem_seo_formulario_e_csrf(): void
    {
        $this->get('/planilha-manutencao-preventiva')
            ->assertOk()
            ->assertSee('<title>Planilha de manutenção preventiva grátis (Excel) | Oravel</title>', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="aceite"', false)
            ->assertSee('href="/politica-de-privacidade/"', false)
            ->assertSee('Desenvolvimento e tecnologia')
            ->assertSee('#F26B1D', false)
            ->assertSee('Manrope', false);
    }

    public function test_envio_salva_lead_com_origem_e_enfileira_email_com_anexo(): void
    {
        Mail::fake();

        $this->post('/planilha-manutencao-preventiva', $this->dados)
            ->assertRedirect(route('isca.planilha').'#baixar')
            ->assertSessionHas('planilha_enviada', 'maria.isca@example.com');

        $lead = SalesLead::where('email', 'maria.isca@example.com')->firstOrFail();
        $this->assertSame('Locadora Isca Teste', $lead->company_name);
        $this->assertSame('isca-planilha-manutencao', $lead->inbound_details['origem']);
        $this->assertStringContainsString('Aceitou receber o material', $lead->inbound_details['aceite_privacidade']);

        Mail::assertQueued(MaterialGratuitoMail::class, function (MaterialGratuitoMail $m) {
            $anexos = $m->attachments();
            $this->assertCount(1, $anexos);
            $this->assertFileExists(MaterialGratuitoMail::caminho('isca-planilha-manutencao'));
            $this->assertStringContainsString('storage/app/private', MaterialGratuitoMail::caminho('isca-planilha-manutencao'));

            return $m->hasTo('maria.isca@example.com') && $m->nome === 'Maria Souza' && $m->origem === 'isca-planilha-manutencao';
        });
    }

    public function test_confirmacao_aparece_na_propria_pagina_depois_do_envio(): void
    {
        Mail::fake();

        $this->followingRedirects()->post('/planilha-manutencao-preventiva', $this->dados)
            ->assertOk()
            ->assertSee('Enviamos a planilha')
            ->assertSee('maria.isca@example.com');
    }

    public function test_e_mail_renderiza_com_anexo_real(): void
    {
        $mail = new MaterialGratuitoMail('isca-planilha-manutencao', 'Maria Souza');

        $mail->assertSeeInHtml('Olá, Maria Souza!');
        $mail->assertHasAttachment(Attachment::fromPath(MaterialGratuitoMail::caminho('isca-planilha-manutencao'))->as('Oravel_Controle_Manutencao_Preventiva.xlsx')->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    }

    public function test_validacao_e_nao_duplica_lead_no_mesmo_email(): void
    {
        Mail::fake();

        $this->post('/planilha-manutencao-preventiva', ['email' => 'invalido'])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'company', 'aceite']);
        Mail::assertNothingQueued();

        $this->post('/planilha-manutencao-preventiva', $this->dados);
        $this->post('/planilha-manutencao-preventiva', $this->dados);

        $this->assertSame(1, SalesLead::where('email', 'maria.isca@example.com')->count());
        Mail::assertQueuedCount(2);
    }

    public function test_robo_que_preenche_o_campo_escondido_nao_gera_lead_nem_email(): void
    {
        Mail::fake();

        $this->post('/planilha-manutencao-preventiva', $this->dados + ['website' => 'http://spam'])
            ->assertSessionHas('planilha_enviada');

        $this->assertSame(0, SalesLead::where('email', 'maria.isca@example.com')->count());
        Mail::assertNothingQueued();
    }

    public function test_sem_o_aceite_nao_ha_lead_nem_email(): void
    {
        Mail::fake();
        $dados = $this->dados;
        unset($dados['aceite']);

        $this->post('/planilha-manutencao-preventiva', $dados)->assertSessionHasErrors('aceite');

        $this->assertSame(0, SalesLead::where('email', 'maria.isca@example.com')->count());
        Mail::assertNothingQueued();
    }
}
