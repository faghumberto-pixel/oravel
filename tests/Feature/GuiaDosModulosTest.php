<?php

namespace Tests\Feature;

use App\Filament\Pages\PrimeirosPassos;
use App\Models\OnboardingProgresso;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Concluídos os primeiros passos, a tela vira o guia das telas importantes de cada módulo contratado. */
class GuiaDosModulosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(string $pacote, array $pacotes = []): User
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true, 'pacotes' => $pacotes ?: null, 'features' => array_fill_keys(Pacotes::featuresDeProdutos($pacotes ?: [$pacote]), true)]);
        $t = Tenant::create(['name' => 'Empresa Guia '.uniqid(), 'slug' => 'g-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'Carlos Souza', 'email' => 'g-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    private function concluir(User $u, string $solucao): void
    {
        foreach (config("onboarding.solucoes.{$solucao}.passos") as $passo) {
            OnboardingProgresso::withoutGlobalScopes()->create(['tenant_id' => $u->tenant_id, 'solution' => $solucao, 'step_key' => $passo['chave'], 'completed_at' => now()]);
        }
    }

    public function test_com_passos_pendentes_a_tela_segue_sendo_o_checklist_e_sem_guia(): void
    {
        $this->actingAs($this->admin('manutencao'));

        Livewire::test(PrimeirosPassos::class)->assertSee('Cadastre seus equipamentos')->assertDontSee('Primeiros passos concluídos');
        $this->assertSame('Primeiros passos', PrimeirosPassos::getNavigationLabel());
    }

    public function test_concluidos_os_passos_some_o_checklist_e_aparecem_as_telas_importantes_do_modulo(): void
    {
        $u = $this->admin('manutencao');
        $this->actingAs($u);
        $this->concluir($u, 'manutencao');

        $t = Livewire::test(PrimeirosPassos::class)->assertSuccessful()
            ->assertSee('Primeiros passos concluídos')->assertSee('Manutenção')->assertSee('Ordens de serviço')->assertSee('Equipamentos')->assertSee('Plano preventivo')
            ->assertDontSee('Cadastre seus equipamentos')->assertDontSee('Passo 3 de 3')->assertDontSee('Travou em algum passo?');

        $this->assertSame('Guia dos módulos', PrimeirosPassos::getNavigationLabel());
        $this->assertTrue(PrimeirosPassos::shouldRegisterNavigation(), 'o item do menu continua, agora como guia');
        $this->assertNotEmpty($t->instance()->guiaDosModulos());
    }

    public function test_guia_lista_um_bloco_por_produto_contratado_e_so_telas_que_o_plano_inclui(): void
    {
        $u = $this->admin('manutencao', ['manutencao', 'vendas']);
        $this->actingAs($u);
        $this->concluir($u, 'manutencao');

        $guia = Livewire::test(PrimeirosPassos::class)->instance()->guiaDosModulos();
        $nomes = array_column($guia, 'nome');

        $this->assertContains('Manutenção', $nomes);
        $this->assertContains('Vendas', $nomes);
        $this->assertNotContains('Estoque', $nomes, 'produto não contratado não aparece');
    }
}
