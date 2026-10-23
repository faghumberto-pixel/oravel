<?php

namespace Tests\Feature;

use App\Filament\Pages\TechnicianDailyTasks;
use App\Livewire\AgendaTecnicoMobile;
use App\Livewire\AssetDemobilizationWizard;
use App\Livewire\AssetMobilizationWizard;
use Livewire\Livewire;
use Tests\Feature\Modulos\Detalhado\PerfisTestCase;

/**
 * Filtros e botoes de selecao das telas de campo (achado em 2026-10-23). O estilo do botao
 * ativo vinha de :class do Alpine ($wire.x), que o Livewire apaga no primeiro re-render: depois
 * de tocar num filtro nenhum botao ficava destacado. Agora o estilo e calculado no servidor.
 * Tambem: "Todos" so zerava um dos 3 filtros de Minhas OS. Rode com DB_DATABASE descartavel.
 */
class FiltrosDasTelasDeCampoTest extends PerfisTestCase
{
    public function test_minhas_os_destaca_o_filtro_ativo_no_servidor(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test(TechnicianDailyTasks::class)
            ->assertSeeHtml('bg-blue-600 text-white')            // "Todos" ativo de partida
            ->set('filterCriticality', 'A')
            ->assertSeeHtml('bg-red-600 text-white')             // "Urgente" destacado depois do re-render
            ->assertDontSeeHtml('transition active:scale-95 bg-blue-600 text-white');
    }

    public function test_todos_limpa_os_tres_filtros(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test(TechnicianDailyTasks::class)
            ->set('filterType', 'maintenance')->set('filterCriticality', 'B')->set('filterNature', 'external')
            ->call('limparFiltros')
            ->assertSet('filterType', '')->assertSet('filterCriticality', '')->assertSet('filterNature', '');
    }

    public function test_tocar_de_novo_no_filtro_ativo_oferece_desligar(): void
    {
        $this->actingAs($this->a['admin']);

        $html = Livewire::test(TechnicianDailyTasks::class)->set('filterCriticality', 'A')->html();

        $this->assertStringContainsString("\$set('filterCriticality', '')", $html);
    }

    public function test_agenda_destaca_o_filtro_e_a_ordenacao_ativos(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test(AgendaTecnicoMobile::class)
            ->set('filterType', 'order')->set('sortBy', 'urgency')
            ->assertSeeHtml('bg-blue-600 text-white')
            ->assertSeeHtml('bg-zinc-800 text-white');
    }

    public function test_combustivel_da_mobilizacao_e_desmobilizacao_destaca_a_escolha(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test(AssetMobilizationWizard::class)->set('step', 3)->set('fuelLevelStart', 'empty')->assertSeeHtml('bg-red-600 text-white');
        Livewire::test(AssetDemobilizationWizard::class)->set('step', 3)->set('fuelLevelEnd', 'empty')->assertSeeHtml('bg-red-600 text-white');
    }

    public function test_nenhuma_tela_usa_class_do_alpine_ligada_a_propriedade_do_livewire(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $arquivo) {
            if (! str_ends_with($arquivo->getFilename(), '.blade.php')) {
                continue;
            }
            $this->assertStringNotContainsString(
                ':class="$wire.',
                file_get_contents($arquivo->getPathname()),
                $arquivo->getFilename().': :class com $wire e apagado pelo re-render do Livewire; calcule a classe no Blade'
            );
        }
    }
}
