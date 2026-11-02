<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ProspectResource;
use App\Filament\Central\Resources\ProspectResource\Pages\EditProspect;
use App\Filament\Central\Resources\ProspectResource\Pages\ListProspects;
use App\Filament\Central\Resources\ProspectResource\RelationManagers\ContatosRelationManager;
use App\Models\Prospect;
use App\Models\SalesLead;
use App\Models\User;
use App\Services\ProspectToLead;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProspectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    private function prospect(array $o = []): Prospect
    {
        return Prospect::create($o + ['cnpj' => '11.111.111/0001-'.random_int(10, 99), 'nome' => 'Obra Mais', 'socio' => 'FRANCO DE ANDRADE', 'ano_abertura' => 2025, 'telefone' => '(19) 98888-7777', 'nota' => 9]);
    }

    public function test_mensagem_usa_primeiro_nome_empresa_e_ano(): void
    {
        $m = $this->prospect()->mensagemAbordagem();
        $this->assertStringContainsString('Olá, Franco.', $m);
        $this->assertStringContainsString('a Obra Mais abriu em 2025', $m);
    }

    public function test_celular_x_fixo(): void
    {
        $this->assertTrue($this->prospect()->ehCelular());
        $this->assertFalse($this->prospect(['telefone' => '(19) 3833-2432'])->ehCelular());
    }

    public function test_super_admin_lista_e_marca_enviei(): void
    {
        $p = $this->prospect();
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);
        Livewire::actingAs($u)->test(ListProspects::class)->assertCanSeeTableRecords([$p])
            ->callTableAction('enviei', $p);
        $p->refresh();
        $this->assertSame('enviado', $p->status);
        $this->assertNotNull($p->proximo_contato);
        $this->assertCount(1, $p->contatos);
    }

    public function test_nao_super_admin_nao_acessa(): void
    {
        $u = User::factory()->create(['email' => Str::random(8).'@x.test']);
        $this->actingAs($u);
        $this->assertFalse(ProspectResource::canViewAny());
    }

    public function test_modal_da_mensagem_mostra_as_objecoes(): void
    {
        $p = $this->prospect();
        $html = view('filament.central.prospect-mensagem', ['abordagem' => $p->mensagemAbordagem(), 'followup' => $p->mensagemFollowUp(), 'objecoes' => Prospect::OBJECOES])->render();
        $this->assertStringContainsString('Se o sócio responder', $html);
        $this->assertStringContainsString('R$ 280 por mês', $html);
    }

    public function test_filtro_de_ano_e_card_aplicam_o_mesmo_recorte(): void
    {
        $velha = $this->prospect(['ano_abertura' => 2010, 'nome' => 'Velha']);
        $nova = $this->prospect(['ano_abertura' => 2025, 'nome' => 'Nova', 'empresa_nova' => true]);
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);

        Livewire::actingAs($u)->test(ListProspects::class)
            ->filterTable('ano_abertura', ['de' => 2024, 'ate' => null])
            ->assertCanSeeTableRecords([$nova])->assertCanNotSeeTableRecords([$velha])
            ->call('filtrarCard', 'nova')
            ->assertCanSeeTableRecords([$nova])->assertCanNotSeeTableRecords([$velha])
            ->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$nova])->assertCanNotSeeTableRecords([$velha]);
    }

    public function test_cards_refletem_filtros_e_busca_e_clique_mantem_filtros_base(): void
    {
        $a = $this->prospect(['nome' => 'Alfa Locacao', 'porte' => 'EPP', 'status' => 'enviado']);
        $b = $this->prospect(['nome' => 'Beta Locacao', 'porte' => 'EPP', 'status' => 'nao_enviado']);
        $c = $this->prospect(['nome' => 'Gama Locacao', 'porte' => 'ME', 'status' => 'enviado']);
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);

        $totais = fn ($t) => collect($t->instance()->cards())->pluck('total', 'chave');

        $t = Livewire::actingAs($u)->test(ListProspects::class);
        $base = $totais($t);

        $t->filterTable('porte', 'EPP');
        $cards = $totais($t);
        $this->assertSame($base['total'] - 1, $cards['total']);
        $this->assertSame($base['enviado'] - 1, $cards['enviado']);

        $t->call('filtrarCard', 'enviado')
            ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b, $c]);

        $t->searchTable('Alfa');
        $this->assertSame(1, $totais($t)['total']);
        $this->assertSame(0, $totais($t)['nao_enviado']);
    }

    public function test_impressao_respeita_filtros_e_so_super_admin(): void
    {
        $this->prospect(['nome' => 'Velha Locadora', 'ano_abertura' => 2010]);
        $this->prospect(['nome' => 'Nova Locadora', 'ano_abertura' => 2025]);
        $query = ['filtros' => ['ano_abertura' => ['de' => 2024]]];

        $this->actingAs(User::factory()->create(['email' => Str::random(8).'@x.test']))
            ->get(route('central.prospects.imprimir', $query))->assertForbidden();

        $r = $this->actingAs(User::factory()->create(['email' => 'equipe@oravel.test']))->get(route('central.prospects.imprimir', $query));
        $r->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $r->headers->get('Content-Disposition'));
        $this->assertSame(1, Prospect::filtrar($query['filtros'])->count());
    }

    public function test_mensagem_propria_substitui_a_padrao_e_modal_mostra_o_que_olhar(): void
    {
        $p = $this->prospect(['mensagem_personalizada' => 'Texto sob medida', 'o_que_olhar' => 'Veja o Instagram']);
        $this->assertSame('Texto sob medida', $p->mensagemAbordagem());
        $html = view('filament.central.prospect-mensagem', ['abordagem' => $p->mensagemAbordagem(), 'followup' => '', 'objecoes' => [], 'olhar' => $p->o_que_olhar])->render();
        $this->assertStringContainsString('Antes de enviar', $html);
        $this->assertStringContainsString('Veja o Instagram', $html);
    }

    public function test_filtro_por_origem_na_tabela_e_na_impressao(): void
    {
        $a = $this->prospect(['nome' => 'Da Lista A', 'origem' => 'Lista A']);
        $b = $this->prospect(['nome' => 'Da Lista B', 'origem' => 'Lista B']);
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);

        Livewire::actingAs($u)->test(ListProspects::class)
            ->filterTable('origem', 'Lista A')
            ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);

        $this->assertSame([$a->id], Prospect::filtrar(['origem' => ['value' => 'Lista A']])->pluck('id')->all());
    }

    public function test_converter_em_lead_cria_lead_com_contatos_e_e_idempotente(): void
    {
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);
        $p = $this->prospect(['origem' => 'Lista A', 'socio_cargo' => 'Sócio-Administrador', 'proximo_contato' => '2026-12-20']);
        $p->contatos()->create(['data' => '2026-11-02', 'canal' => 'whatsapp', 'titulo' => 'Primeiro contato', 'texto' => 'Enviei a mensagem.']);

        $lead = ProspectToLead::converter($p, $u);
        $again = ProspectToLead::converter($p->fresh(), $u);

        $this->assertSame($lead->id, $again->id);
        $this->assertSame(1, SalesLead::where('company_name', 'Obra Mais')->count());
        $this->assertSame(SalesLead::STAGE_PROSPECCAO, $lead->pipeline_stage);
        $this->assertSame('prospeccao_ativa', $lead->source);
        $this->assertSame('2026-12-20', $lead->next_followup_date->toDateString());
        $this->assertSame('FRANCO DE ANDRADE', $lead->decision_makers[0]['name']);
        $this->assertSame(2, $lead->interactions()->count()); // ficha + 1 contato
        $this->assertNull($lead->blockerForNextStage());       // ja pode avancar no funil
        $this->assertSame('convertido', $p->fresh()->status);
        $this->assertSame($lead->id, $p->fresh()->sales_lead_id);
    }

    public function test_acao_na_tabela_e_no_fim_dos_contatos(): void
    {
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);
        $p = $this->prospect(['nome' => 'Via Tabela']);
        Livewire::actingAs($u)->test(ListProspects::class)->callTableAction('converterEmLead', $p);
        $this->assertNotNull($p->fresh()->sales_lead_id);

        $q = $this->prospect(['nome' => 'Via Contatos']);
        Livewire::actingAs($u)->test(ContatosRelationManager::class, ['ownerRecord' => $q, 'pageClass' => EditProspect::class])
            ->callTableAction('converterEmLead');
        $this->assertNotNull($q->fresh()->sales_lead_id);
    }

    public function test_cada_card_tem_cor_propria_por_significado(): void
    {
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);

        $t = Livewire::actingAs($u)->test(ListProspects::class);
        $cards = collect($t->instance()->cards())->keyBy('chave');

        $this->assertSame('bg-blue-50 dark:bg-blue-500/25', $cards['enviado']['paleta']['soft']);
        $this->assertSame('bg-emerald-50 dark:bg-emerald-500/25', $cards['respondeu']['paleta']['soft']);
        $this->assertSame('bg-red-50 dark:bg-red-500/25', $cards['sem_interesse']['paleta']['soft']);
        $t->assertSeeHtml('bg-emerald-50');
    }

    public function test_proximo_contato_vira_compromisso_na_programacao_e_topo_conta(): void
    {
        $p = $this->prospect(['nome' => 'Locadora Teste']);
        $this->assertSame(0, $p->compromissos()->count());

        $p->update(['proximo_contato' => now()->addDay()->toDateString()]);
        $this->assertSame(1, $p->compromissos()->count());
        $c = $p->compromissos()->first();
        $this->assertSame('pendente', $c->status);
        $this->assertNull($c->sales_lead_id);
        $this->assertSame('Locadora Teste', $c->empresaNome());

        // mudar a data move o mesmo compromisso, sem duplicar
        $p->update(['proximo_contato' => now()->addDays(3)->toDateString()]);
        $this->assertSame(1, $p->compromissos()->count());
        $this->assertTrue($p->compromissos()->first()->scheduled_at->isSameDay(now()->addDays(3)));

        // apagar a data remove o compromisso pendente
        $p->update(['proximo_contato' => null]);
        $this->assertSame(0, $p->compromissos()->count());

        // topo: hoje e semana
        $p->update(['proximo_contato' => now()->toDateString()]);
        $html = view('filament.central.topbar-clock')->render();
        $this->assertStringContainsString('na semana', $html);
        $this->assertStringContainsString('visao=hoje', $html);
        $this->assertStringContainsString('visao=semana', $html);
    }
}
