<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource\Pages\CreateContrato;
use App\Filament\Central\Resources\ContratoResource\Pages\EditContrato;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Site e e-mail institucional como opção do Contrato (Central): nao | incluso (grátis no contrato de 12 meses, acompanha
 * o contrato) | avulso (valor único, padrão R$ 500). Quando ligado, tem que constar no contrato assinado (Anexo III).
 */
class SiteEmailInstitucionalContratoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    private function plano(array $extra = []): Plan
    {
        return Plan::create($extra + ['name' => 'Contrato Site '.uniqid(), 'price' => 280, 'base_price' => 280, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients' => true]]);
    }

    private function tenant(Plan $plano, array $extra = []): Tenant
    {
        return Tenant::create($extra + ['name' => 'Empresa Site '.uniqid(), 'slug' => 'site-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    private function anexo(Tenant $t): string
    {
        return view('partials.subscription-agreement-site-email', ['contract' => $t->fresh()->load('plan')])->render();
    }

    public function test_padrao_do_contrato_e_nao_incluido_e_o_valor_avulso_padrao_e_500(): void
    {
        $p = $this->plano();

        $this->assertSame('nao', $p->fresh()->siteEmailModo());
        $this->assertFalse($p->fresh()->temSiteEmail());
        $this->assertSame(500.0, $p->siteEmailValor());
        $this->assertSame(750.0, $this->plano(['site_email_valor' => 750])->siteEmailValor());
        $this->assertSame('nao', $this->plano(['site_email_modo' => 'qualquer-coisa'])->siteEmailModo());
    }

    public function test_sem_site_e_email_o_contrato_nao_tem_anexo_iii(): void
    {
        $t = $this->tenant($this->plano());

        $this->assertStringNotContainsString('ANEXO III', $this->anexo($t));
    }

    public function test_incluso_consta_no_contrato_como_gratuito_de_12_meses_que_renova_com_o_contrato(): void
    {
        $t = $this->tenant($this->plano(['site_email_modo' => 'incluso']));
        $html = $this->anexo($t);

        $this->assertStringContainsString('ANEXO III — SITE E E-MAIL INSTITUCIONAL', $html);
        $this->assertStringContainsString('sem custo adicional', $html);
        $this->assertStringContainsString('12 (doze) meses', $html);
        $this->assertStringContainsString('Renovam-se automaticamente', $html);
        $this->assertStringContainsString('mensalidades do período estejam pagas', $html);
        $this->assertStringContainsString('acompanham o contrato', $html);
        $this->assertStringNotContainsString('R$ 500,00', $html);
    }

    public function test_avulso_consta_com_o_valor_unico_e_respeita_o_valor_do_contrato(): void
    {
        $padrao = $this->anexo($this->tenant($this->plano(['site_email_modo' => 'avulso'])));
        $this->assertStringContainsString('R$ 500,00', $padrao);
        $this->assertStringContainsString('único', $padrao);
        $this->assertStringContainsString('não recorrente', $padrao);
        $this->assertStringNotContainsString('sem custo adicional', $padrao);

        $this->assertStringContainsString('R$ 750,00', $this->anexo($this->tenant($this->plano(['site_email_modo' => 'avulso', 'site_email_valor' => 750]))));
    }

    public function test_cliente_que_ja_tem_site_continua_com_a_oferta_e_o_escopo_combinado_aparece(): void
    {
        $html = $this->anexo($this->tenant($this->plano(['site_email_modo' => 'incluso', 'site_email_cliente_possui' => true, 'site_email_escopo' => 'Site de 5 páginas e 3 caixas de e-mail'])));

        $this->assertStringContainsString('Ainda que o Contratante já possua site e e-mail próprios', $html);
        $this->assertStringContainsString('Site de 5 páginas e 3 caixas de e-mail', $html);

        $semEscopo = $this->anexo($this->tenant($this->plano(['site_email_modo' => 'incluso'])));
        $this->assertStringContainsString('descrito na proposta comercial aceita', $semEscopo);
        $this->assertStringNotContainsString('Ainda que o Contratante', $semEscopo);
    }

    public function test_situacao_na_central_acompanha_o_contrato(): void
    {
        $this->assertSame('Não incluído', $this->tenant($this->plano())->siteEmailSituacao()['label']);

        $incluso = $this->tenant($this->plano(['site_email_modo' => 'incluso']));
        $this->assertSame(['label' => 'Incluso (sem custo)', 'color' => 'success'], $incluso->siteEmailSituacao());

        $avulso = $this->tenant($this->plano(['site_email_modo' => 'avulso']));
        $this->assertSame('Vendido à parte (R$ 500,00)', $avulso->siteEmailSituacao()['label']);

        // pagamento cancelado: o site e e-mail param junto com o contrato
        $cancelado = $this->tenant($this->plano(['site_email_modo' => 'incluso']), ['asaas_payment_status' => Tenant::PAYMENT_STATUS_CANCELADO]);
        $this->assertSame(['label' => 'Incluso (sem custo) — suspenso', 'color' => 'danger'], $cancelado->siteEmailSituacao());
    }

    public function test_central_cria_e_edita_contrato_com_a_opcao_e_exige_valor_no_avulso(): void
    {
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);

        // avulso sem valor: nao salva
        Livewire::actingAs($u)->test(CreateContrato::class)
            ->fillForm(['name' => 'Cliente Avulso', 'base_price' => 280, 'billing_cycle' => 'monthly', 'site_email_modo' => 'avulso', 'site_email_valor' => null])
            ->call('create')
            ->assertHasFormErrors(['site_email_valor']);

        Livewire::actingAs($u)->test(CreateContrato::class)
            ->fillForm(['name' => 'Cliente Avulso', 'base_price' => 280, 'billing_cycle' => 'monthly', 'site_email_modo' => 'avulso', 'site_email_valor' => 500, 'site_email_cliente_possui' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $p = Plan::where('name', 'Cliente Avulso')->firstOrFail();
        $this->assertSame('avulso', $p->site_email_modo);
        $this->assertSame(500.0, $p->siteEmailValor());
        $this->assertTrue($p->site_email_cliente_possui);

        // incluso nao pede valor
        Livewire::actingAs($u)->test(CreateContrato::class)
            ->fillForm(['name' => 'Cliente Incluso', 'base_price' => 280, 'billing_cycle' => 'monthly', 'site_email_modo' => 'incluso'])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame('incluso', Plan::where('name', 'Cliente Incluso')->firstOrFail()->site_email_modo);

        // edicao: desligar
        Livewire::actingAs($u)->test(EditContrato::class, ['record' => $p->getRouteKey()])
            ->fillForm(['site_email_modo' => 'nao'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('nao', $p->fresh()->site_email_modo);
    }

    public function test_pdf_completo_do_contrato_mostra_o_item_no_plano_e_o_anexo(): void
    {
        $com = view('pdf.subscription-agreement', ['contract' => $this->tenant($this->plano(['site_email_modo' => 'avulso']))->load('plan')])->render();
        $this->assertStringContainsString('Site e e-mail institucional', $com);
        $this->assertStringContainsString('R$ 500,00 (cobrança única)', $com);
        $this->assertStringContainsString('ANEXO III', $com);

        $sem = view('pdf.subscription-agreement', ['contract' => $this->tenant($this->plano())->load('plan')])->render();
        $this->assertStringNotContainsString('Site e e-mail institucional', $sem);
        $this->assertStringNotContainsString('ANEXO III', $sem);
    }
}
