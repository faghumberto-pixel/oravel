<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\SalesLead;
use App\Models\User;
use App\Relacionamento\Filament\RelationManagers\InteracoesDoContatoRelationManager;
use App\Relacionamento\Filament\Resources\RelContatoResource;
use App\Relacionamento\Filament\Resources\RelContatoResource\Pages\ListRelContatos;
use App\Relacionamento\Filament\Resources\RelContatoResource\Pages\ViewRelContato;
use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Models\RelInteracao;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Relacionamento Oravel: tela de Contatos (assinantes/visitantes), vinculo a empresa e exclusao (LGPD). */
class RelacionamentoContatosTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        return User::factory()->create(['email' => 'equipe@oravel.test']);
    }

    private function contato(string $email, array $extra = []): RelContato
    {
        return RelContato::create($extra + ['email' => $email, 'nome' => 'Pessoa '.$email]);
    }

    private function inter(RelContato $c, string $tipo, array $extra = []): RelInteracao
    {
        return RelInteracao::create($extra + ['contato_id' => $c->id, 'tipo' => $tipo, 'ocorrido_em' => now()->subDay()]);
    }

    public function test_so_super_admin_ve_a_tela(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'comum@x.test']));
        $this->assertFalse(RelContatoResource::canViewAny());

        $this->actingAs($this->admin());
        $this->assertTrue(RelContatoResource::canViewAny());
    }

    public function test_lista_mostra_contatos_com_score_e_filtra_sem_empresa(): void
    {
        $u = $this->admin();
        $p = Prospect::create(['cnpj' => '11222333000144', 'nome' => 'Locadora A']);
        $com = $this->contato('com@x.com', ['prospect_id' => $p->id]);
        $sem = $this->contato('sem@x.com');
        $this->inter($sem, 'ferramenta');
        $this->inter($sem, 'video');

        $t = Livewire::actingAs($u)->test(ListRelContatos::class)->assertCanSeeTableRecords([$com, $sem]);
        $t->filterTable('sem_empresa', ['isActive' => true])->assertCanSeeTableRecords([$sem])->assertCanNotSeeTableRecords([$com]);

        $this->assertSame(30, RelContatoResource::score($sem)['score']); // ferramenta 20 + video 10
    }

    public function test_filtra_quem_aceitou_receber(): void
    {
        $u = $this->admin();
        $aceitou = $this->contato('sim@x.com', ['consentimento_em' => now(), 'consentimento_origem' => 'sender-form']);
        $nao = $this->contato('nao@x.com');

        Livewire::actingAs($u)->test(ListRelContatos::class)
            ->filterTable('com_consentimento', ['isActive' => true])->assertCanSeeTableRecords([$aceitou])->assertCanNotSeeTableRecords([$nao]);
    }

    public function test_vincular_a_prospect_leva_o_historico_da_pessoa_para_a_empresa(): void
    {
        $u = $this->admin();
        $p = Prospect::create(['cnpj' => '11222333000144', 'nome' => 'Locadora B']);
        $c = $this->contato('pessoa@x.com');
        $i = $this->inter($c, 'artigo');
        $outra = $this->contato('outra@x.com');
        $alheia = $this->inter($outra, 'artigo');

        Livewire::actingAs($u)->test(ListRelContatos::class)
            ->callTableAction('vincular', $c, data: ['prospect_id' => $p->id])->assertHasNoTableActionErrors();

        $this->assertSame($p->id, $c->fresh()->prospect_id);
        $this->assertSame($p->id, $i->fresh()->prospect_id);
        $this->assertNull($alheia->fresh()->prospect_id);
    }

    public function test_vincular_a_prospect_que_ja_virou_lead_liga_o_lead_tambem(): void
    {
        $u = $this->admin();
        $lead = SalesLead::create(['company_name' => 'Lead de teste', 'pipeline_stage' => SalesLead::STAGE_PROSPECCAO]);
        $p = Prospect::create(['cnpj' => '11222333000155', 'nome' => 'Locadora C', 'sales_lead_id' => $lead->id]);
        $c = $this->contato('lead@x.com');

        Livewire::actingAs($u)->test(ListRelContatos::class)->callTableAction('vincular', $c, data: ['prospect_id' => $p->id]);

        $this->assertSame($lead->id, $c->fresh()->sales_lead_id);
    }

    public function test_excluir_apaga_a_pessoa_e_todo_o_historico_dela_e_so_dela(): void
    {
        $u = $this->admin();
        $c = $this->contato('apagar@x.com');
        $minha = $this->inter($c, 'artigo');
        $outra = $this->contato('fica@x.com');
        $alheia = $this->inter($outra, 'video');

        Livewire::actingAs($u)->test(ListRelContatos::class)->callTableAction('delete', $c);

        $this->assertNull(RelContato::find($c->id));
        $this->assertNull(RelInteracao::find($minha->id));
        $this->assertNotNull(RelInteracao::find($alheia->id));
        $this->assertNotNull(RelContato::find($outra->id));
    }

    public function test_ficha_do_contato_abre_e_a_aba_mostra_so_as_interacoes_dele(): void
    {
        $u = $this->admin();
        $c = $this->contato('ficha@x.com');
        $minha = $this->inter($c, 'ferramenta', ['titulo' => 'Minha']);
        $outra = $this->inter($this->contato('o@x.com'), 'ferramenta', ['titulo' => 'Alheia']);

        Livewire::actingAs($u)->test(ViewRelContato::class, ['record' => $c->getKey()])->assertSuccessful();

        $t = Livewire::actingAs($u)->test(InteracoesDoContatoRelationManager::class, ['ownerRecord' => $c, 'pageClass' => ViewRelContato::class])
            ->assertCanSeeTableRecords([$minha])->assertCanNotSeeTableRecords([$outra]);
        $this->assertStringContainsString('Relacionamento: 20/100', $t->instance()->resumo());
        $this->assertStringNotContainsString('Interesses', $t->instance()->resumo());
    }
}
