<?php

namespace Tests\Feature;

use App\Filament\Central\Pages\AcompanhamentoSite;
use App\Filament\Central\Widgets\Acompanhamento\NotasTable;
use App\Filament\Central\Widgets\Acompanhamento\PaginasTable;
use App\Filament\Central\Widgets\Acompanhamento\PendenciasTable;
use App\Models\Plan;
use App\Models\SiteAcompanhamentoNota;
use App\Models\SiteAcompanhamentoPagina;
use App\Models\SiteAcompanhamentoPendencia;
use App\Models\Tenant;
use App\Models\User;
use Filament\Tables\Actions\CreateAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pagina do painel Central para acompanhar a remodelagem do site: previa + notas, status por pagina e
 * pendencias. So' super admin; tabelas globais.
 */
class AcompanhamentoSiteTest extends TestCase
{
    use DatabaseTransactions;

    private function pessoa(string $email): User
    {
        $plan = Plan::create([
            'name' => 'Plano '.Str::random(6), 'price' => 1, 'base_price' => 1, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);
        $t = Tenant::create(['name' => 'Cliente '.Str::random(5), 'slug' => 'c-'.Str::random(6), 'plan_id' => $plan->id, 'status' => 'active']);

        return User::create(['name' => 'Pessoa', 'email' => $email, 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
    }

    public function test_so_super_admin_acessa_a_pagina(): void
    {
        $this->actingAs($this->pessoa('comum-'.Str::random(4).'@cliente.test'));
        $this->assertFalse(AcompanhamentoSite::canAccess());

        $this->actingAs($this->pessoa('equipe@oravel.test'));
        $this->assertTrue(AcompanhamentoSite::canAccess());
    }

    public function test_pagina_renderiza_com_previa_e_tabelas(): void
    {
        $this->actingAs($this->pessoa('equipe@oravel.test'));

        Livewire::test(AcompanhamentoSite::class)
            ->assertSee('Prévia do site novo')
            ->assertSeeHtml(route('central.previa-site'));
    }

    public function test_previa_so_abre_para_super_admin(): void
    {
        $this->actingAs($this->pessoa('comum-'.Str::random(4).'@cliente.test'))->get(route('central.previa-site'))->assertForbidden();
        $this->get(route('central.previa-site'))->assertForbidden();

        auth()->logout();
        $this->actingAs($this->pessoa('equipe@oravel.test'))->get(route('central.previa-site'))->assertOk();
    }

    public function test_notas_criar_e_listar(): void
    {
        $this->actingAs($this->pessoa('equipe@oravel.test'));
        $antes = SiteAcompanhamentoNota::count();

        Livewire::test(NotasTable::class)
            ->callTableAction(CreateAction::class, data: ['data' => '2026-11-01', 'titulo' => 'Teste', 'texto' => 'Detalhes do acompanhamento'])
            ->assertHasNoTableActionErrors();

        $this->assertSame($antes + 1, SiteAcompanhamentoNota::count());
        $nota = SiteAcompanhamentoNota::where('titulo', 'Teste')->firstOrFail();
        $this->assertSame('Pessoa', $nota->autor);

        Livewire::test(NotasTable::class)->searchTable('Teste')->assertCanSeeTableRecords([$nota]);
    }

    public function test_pendencias_concluir(): void
    {
        $this->actingAs($this->pessoa('equipe@oravel.test'));
        $p = SiteAcompanhamentoPendencia::create(['titulo' => 'Pendencia '.Str::random(5), 'tipo' => 'tarefa', 'status' => 'aberta']);

        Livewire::test(PendenciasTable::class)
            ->searchTable($p->titulo)
            ->assertCanSeeTableRecords([$p])
            ->callTableAction('concluir', $p);

        $this->assertSame('concluida', $p->fresh()->status);
    }

    public function test_paginas_atualizar_status_pela_tabela(): void
    {
        $this->actingAs($this->pessoa('equipe@oravel.test'));
        $pg = SiteAcompanhamentoPagina::create(['slug' => '/teste-'.Str::random(6).'/', 'nome' => 'Teste', 'status' => 'rascunho']);

        Livewire::test(PaginasTable::class)->searchTable($pg->slug)->assertCanSeeTableRecords([$pg]);

        $pg->update(['status' => 'aprovada']);
        $this->assertSame('aprovada', $pg->fresh()->status);
    }

    public function test_widgets_nao_aparecem_para_quem_nao_e_super_admin(): void
    {
        $this->actingAs($this->pessoa('comum-'.Str::random(4).'@cliente.test'));

        foreach ([NotasTable::class, PaginasTable::class, PendenciasTable::class] as $w) {
            $this->assertFalse($w::canView());
        }
    }
}
