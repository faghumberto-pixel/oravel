<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Trial\Services\TrialCadastro;
use App\Livewire\TourGuiado;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTour;
use App\Support\Tour\Tour;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class TourGuiadoTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $whats = '+5511999990101'): User
    {
        Mail::fake();

        return app(TrialCadastro::class)->cadastrar(['nome' => 'Maria Teste', 'empresa' => 'Locadora Tour '.$whats, 'whatsapp' => $whats])['trial']->user;
    }

    /** Caminho (sem barra) da tela de um passo do tour, como o Filament o serve. */
    private function caminho(User $u, string $chave): string
    {
        $this->actingAs($u);
        $passo = collect(Tour::para($u)->passos())->firstWhere('chave', $chave);
        $this->assertNotNull($passo, "o usuário não enxerga a tela $chave");

        return trim((string) parse_url($passo['url'], PHP_URL_PATH), '/');
    }

    public function test_abre_sozinho_na_primeira_visita_a_tela_e_explica_por_que_quando_e_como(): void
    {
        $u = $this->usuario();
        $caminho = $this->caminho($u, 'indice');

        Livewire::test(TourGuiado::class, ['caminho' => $caminho])
            ->assertSet('aberto', true)
            ->assertSee('O que é esta tela')
            ->assertSee('Índice de Saúde')
            ->assertSee('Por que usar')->assertSee('Quando usar')->assertSee('Como fazer');

        $this->assertTrue(UserTour::where('user_id', $u->id)->first()->jaViu('indice'));
    }

    public function test_cada_tela_abre_uma_unica_vez_e_cada_uma_tem_o_seu_pop_up(): void
    {
        $u = $this->usuario();
        $indice = $this->caminho($u, 'indice');
        $diag = $this->caminho($u, 'diagnostico');

        Livewire::test(TourGuiado::class, ['caminho' => $indice])->assertSet('aberto', true);
        Livewire::test(TourGuiado::class, ['caminho' => $indice])->assertSet('aberto', false);   // segunda visita: não repete
        Livewire::test(TourGuiado::class, ['caminho' => $diag])->assertSet('aberto', true)->assertSee('Diagnóstico')->assertDontSee('Índice de Saúde: como está a locadora');
    }

    public function test_botao_reabre_a_explicacao_da_tela_atual(): void
    {
        $u = $this->usuario();
        $caminho = $this->caminho($u, 'equipamentos');
        $c = Livewire::test(TourGuiado::class, ['caminho' => $caminho]);

        $c->call('fechar')->assertSet('aberto', false)->call('abrir')->assertSet('aberto', true)->assertSee('Equipamentos');
        $this->assertSame(1, UserTour::where('user_id', $u->id)->first()->reaberturas);
    }

    public function test_botao_fora_das_telas_do_tour_leva_a_primeira_tela_com_o_pop_up_aberto(): void
    {
        $u = $this->usuario();
        $this->actingAs($u);
        $primeira = Tour::para($u)->passos()[0]['url'];

        Livewire::test(TourGuiado::class, ['caminho' => 'admin/qualquer-outra-tela'])
            ->assertSet('aberto', false)
            ->call('abrir')->assertRedirect($primeira.'?tour=abrir');
    }

    public function test_parametro_tour_abrir_abre_mesmo_se_a_tela_ja_foi_vista(): void
    {
        $u = $this->usuario();
        $caminho = $this->caminho($u, 'plano');
        Livewire::test(TourGuiado::class, ['caminho' => $caminho]);                                  // 1ª visita

        Livewire::withQueryParams(['tour' => 'abrir'])->test(TourGuiado::class, ['caminho' => $caminho])->assertSet('aberto', true);
    }

    public function test_leva_a_tela_seguinte_e_a_anterior_na_ordem_do_config(): void
    {
        $u = $this->usuario();
        $this->actingAs($u);
        $passos = Tour::para($u)->passos();
        $caminho = $this->caminho($u, $passos[1]['chave']);

        Livewire::test(TourGuiado::class, ['caminho' => $caminho])
            ->assertSee('Próxima tela')->assertSee($passos[2]['url'].'?tour=abrir', false)
            ->assertSee('Tela anterior')->assertSee($passos[0]['url'].'?tour=abrir', false)
            ->assertSee('2 de '.count($passos));
    }

    public function test_primeira_e_ultima_tela_nao_tem_anterior_nem_proxima(): void
    {
        $u = $this->usuario();
        $this->actingAs($u);
        $passos = Tour::para($u)->passos();

        Livewire::test(TourGuiado::class, ['caminho' => $this->caminho($u, $passos[0]['chave'])])->assertDontSee('Tela anterior')->assertSee('Próxima tela');
        Livewire::test(TourGuiado::class, ['caminho' => $this->caminho($u, end($passos)['chave'])])->assertDontSee('Próxima tela')->assertSee('Concluir');
    }

    public function test_nao_abrir_sozinho_de_novo_vale_para_as_demais_telas_mas_o_botao_continua(): void
    {
        $u = $this->usuario();
        $indice = $this->caminho($u, 'indice');
        $plano = $this->caminho($u, 'plano');

        Livewire::test(TourGuiado::class, ['caminho' => $indice])->call('naoMostrarMais')->assertSet('aberto', false);

        Livewire::test(TourGuiado::class, ['caminho' => $plano])->assertSet('aberto', false)->call('abrir')->assertSet('aberto', true);
    }

    public function test_super_admin_nunca_abre_sozinho(): void
    {
        $u = $this->usuario();
        $caminho = $this->caminho($u, 'indice');
        config(['oravel.super_admins' => [$u->email]]);

        Livewire::test(TourGuiado::class, ['caminho' => $caminho])->assertSet('aberto', false);
    }

    public function test_empresa_sem_o_produto_nao_ve_nada(): void
    {
        $plano = Plan::create(['name' => 'Outro', 'pacote' => 'manutencao', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $tenant = Tenant::create(['name' => 'Manut', 'slug' => 'manut-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($u);

        $this->assertNull(Tour::para($u));
        Livewire::test(TourGuiado::class, ['caminho' => 'admin/intelligence-dashboard'])->assertSet('aberto', false)->assertDontSee('O que é esta tela');
        $this->assertSame('', trim(view('filament.tour-botao')->render()), 'sem botão para quem não tem tour');
    }

    public function test_botao_do_topo_aparece_para_quem_tem_o_tour(): void
    {
        $this->actingAs($this->usuario());
        $this->assertStringContainsString('Passo a passo', view('filament.tour-botao')->render());
    }

    public function test_nota_do_teste_gratis_aparece_na_tela_de_importacoes_para_quem_esta_no_teste(): void
    {
        $u = $this->usuario();
        $caminho = $this->caminho($u, 'importacoes');

        Livewire::test(TourGuiado::class, ['caminho' => $caminho])->assertSee('30 dias começa a contar no seu primeiro envio');
    }

    public function test_conteudo_do_config_esta_completo_e_as_telas_existem(): void
    {
        $chaves = [];
        foreach (config('tour.tours.intelligence.passos') as $p) {
            foreach (['chave', 'titulo', 'resumo', 'porque', 'quando', 'como', 'tela'] as $campo) {
                $this->assertNotEmpty($p[$campo] ?? null, "passo {$p['chave']} sem $campo");
            }
            $this->assertTrue(class_exists($p['tela']), "tela inexistente: {$p['tela']}");
            $chaves[] = $p['chave'];
        }
        $this->assertSame($chaves, array_values(array_unique($chaves)), 'chaves repetidas');
        $this->assertGreaterThanOrEqual(7, count($chaves));

        // quem tem o produto enxerga todas as telas do tour
        $u = $this->usuario('+5511999990102');
        $this->actingAs($u);
        $this->assertCount(count($chaves), Tour::para($u)->passos());
    }
}
