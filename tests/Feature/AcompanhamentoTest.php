<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\AcompanhamentoResource;
use App\Filament\Central\Resources\AcompanhamentoResource\Pages\CreateAcompanhamento;
use App\Filament\Central\Resources\AcompanhamentoResource\Pages\ListAcompanhamentos;
use App\Filament\Central\Resources\AcompanhamentoResource\Pages\ViewAcompanhamento;
use App\Filament\Central\Resources\AcompanhamentoResource\RelationManagers\ItensRelationManager;
use App\Filament\Central\Resources\AcompanhamentoResource\RelationManagers\NotasRelationManager;
use App\Filament\Central\Resources\AcompanhamentoResource\RelationManagers\PendenciasRelationManager;
use App\Models\Acompanhamento;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Actions\CreateAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Acompanhamentos do painel Central: varios acompanhamentos (site, pesquisas), cada um com lista de itens,
 * notas e pendencias; so' super admin; tabelas globais.
 */
class AcompanhamentoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    private function pessoa(string $email): User
    {
        $plan = Plan::create(['name' => 'Plano '.Str::random(6), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $t = Tenant::create(['name' => 'Cliente '.Str::random(5), 'slug' => 'c-'.Str::random(6), 'plan_id' => $plan->id, 'status' => 'active']);

        return User::create(['name' => 'Pessoa', 'email' => $email, 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
    }

    private function super(): User
    {
        return $this->actingAs($this->pessoa('equipe@oravel.test'))->app['auth']->user();
    }

    private function acomp(?string $nome = null): Acompanhamento
    {
        return Acompanhamento::create(['nome' => $nome ?? 'Pesquisa '.Str::random(5), 'tipo' => 'pesquisa', 'base_url' => 'https://exemplo.test']);
    }

    private function itens(Acompanhamento $a): array
    {
        return ['ownerRecord' => $a, 'pageClass' => ViewAcompanhamento::class];
    }

    public function test_so_super_admin_acessa(): void
    {
        $this->actingAs($this->pessoa('comum-'.Str::random(4).'@cliente.test'));
        $this->assertFalse(AcompanhamentoResource::canViewAny());

        $this->actingAs($this->pessoa('equipe@oravel.test'));
        $this->assertTrue(AcompanhamentoResource::canViewAny());
    }

    public function test_cria_novo_acompanhamento_e_lista(): void
    {
        $this->super();

        Livewire::test(CreateAcompanhamento::class)
            ->fillForm(['nome' => 'Pesquisa de concorrentes', 'tipo' => 'pesquisa', 'status' => 'ativo'])
            ->call('create')->assertHasNoFormErrors();

        $a = Acompanhamento::where('nome', 'Pesquisa de concorrentes')->firstOrFail();
        Livewire::test(ListAcompanhamentos::class)->searchTable('Pesquisa de concorrentes')->assertCanSeeTableRecords([$a]);
    }

    public function test_cola_lista_cria_itens_e_ignora_repetidos(): void
    {
        $this->super();
        $a = $this->acomp();

        $lista = "Fontes | Site A | https://a.test\nSite B | /b/\nFontes | Site A | https://a.test\n\nSó o nome";
        Livewire::test(ItensRelationManager::class, $this->itens($a))
            ->callTableAction('colar', data: ['texto' => $lista, 'grupo' => 'Geral', 'status' => 'a_fazer'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(3, $a->itens()->count());
        $this->assertSame('Fontes', $a->itens()->where('nome', 'Site A')->value('grupo'));
        $this->assertSame('Geral', $a->itens()->where('nome', 'Site B')->value('grupo'));
        $this->assertSame('/b/', $a->itens()->where('nome', 'Site B')->value('referencia'));

        // colar de novo nao duplica
        Livewire::test(ItensRelationManager::class, $this->itens($a))
            ->callTableAction('colar', data: ['texto' => $lista, 'grupo' => 'Geral', 'status' => 'a_fazer']);
        $this->assertSame(3, $a->itens()->count());
    }

    public function test_link_do_item_usa_base_url_so_para_caminhos(): void
    {
        $a = $this->acomp();
        $i1 = $a->itens()->create(['nome' => 'a', 'referencia' => '/pag/', 'status' => 'a_fazer']);
        $i2 = $a->itens()->create(['nome' => 'b', 'referencia' => 'https://outro.test/x', 'status' => 'a_fazer']);
        $i3 = $a->itens()->create(['nome' => 'c', 'referencia' => 'sem endereco', 'status' => 'a_fazer']);

        $this->assertSame('https://exemplo.test/pag/', $i1->link());
        $this->assertSame('https://outro.test/x', $i2->link());
        $this->assertNull($i3->link());
    }

    public function test_notas_e_pendencias_ficam_no_acompanhamento_certo(): void
    {
        $this->super();
        $a = $this->acomp();
        $b = $this->acomp();

        Livewire::test(NotasRelationManager::class, $this->itens($a))
            ->callTableAction(CreateAction::class, data: ['data' => '2026-11-01', 'titulo' => 'Nota A', 'texto' => 'Detalhe'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(1, $a->notas()->count());
        $this->assertSame(0, $b->notas()->count());
        $this->assertSame('Pessoa', $a->notas()->first()->autor);

        $p = $a->pendencias()->create(['titulo' => 'Decidir '.Str::random(4), 'tipo' => 'decisao', 'status' => 'aberta']);
        Livewire::test(PendenciasRelationManager::class, $this->itens($a))->callTableAction('concluir', $p);
        $this->assertSame('concluida', $p->fresh()->status);
    }

    public function test_previa_so_para_super_admin_e_com_arquivo(): void
    {
        Storage::disk('local')->put('acompanhamentos/teste.html', '<html><body>previa</body></html>');
        $a = $this->acomp();
        $a->update(['previa_arquivo' => 'acompanhamentos/teste.html']);
        $url = route('central.acompanhamento.previa', $a);

        $this->get($url)->assertRedirect();   // sem login
        $this->actingAs($this->pessoa('comum-'.Str::random(4).'@cliente.test'))->get($url)->assertForbidden();
        $this->actingAs($this->pessoa('equipe@oravel.test'))->get($url)->assertOk();

        $a->update(['previa_arquivo' => null]);
        $this->get($url)->assertNotFound();
        Storage::disk('local')->delete('acompanhamentos/teste.html');
    }

    public function test_pagina_do_acompanhamento_mostra_previa_em_iframe_isolado(): void
    {
        $this->super();
        $a = $this->acomp();
        $a->update(['previa_arquivo' => 'acompanhamentos/x.html']);

        Livewire::test(ViewAcompanhamento::class, ['record' => $a->getKey()])
            ->assertSuccessful()->assertSeeHtml('sandbox="allow-scripts allow-forms allow-popups"');
    }
}
