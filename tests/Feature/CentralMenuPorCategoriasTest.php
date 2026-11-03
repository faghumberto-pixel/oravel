<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CentralMenuPorCategoriasTest extends TestCase
{
    use DatabaseTransactions;

    public function test_menu_da_central_esta_organizado_por_categorias(): void
    {
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));
        $this->actingAs(User::factory()->create(['email' => 'equipe@oravel.test']));

        $grupos = collect(Filament::getNavigation());

        $nomes = $grupos->map(fn ($g) => $g->getLabel())->filter()->values()->all();
        $this->assertSame(
            ['Comercial', 'Prospecção', 'Clientes e Contratos', 'Acessos e Uso', 'Treinamento', 'Site Institucional', 'Segurança e Conformidade', 'Sistema'],
            array_values(array_intersect($nomes, ['Comercial', 'Prospecção', 'Clientes e Contratos', 'Acessos e Uso', 'Treinamento', 'Site Institucional', 'Segurança e Conformidade', 'Sistema'])),
        );
        $this->assertNotContains('Gestão SaaS', $nomes);
        $this->assertNotContains('Conformidade & Compliance', $nomes);

        // Nenhum grupo com mais de 8 itens (antes "Gestão SaaS" tinha 19).
        foreach ($grupos as $g) {
            $this->assertLessThanOrEqual(8, count($g->getItems()), 'Grupo grande demais: '.$g->getLabel());
        }

        // Itens soltos (sem grupo): so o Dashboard SaaS.
        $soltos = $grupos->filter(fn ($g) => ! $g->getLabel())->flatMap(fn ($g) => $g->getItems())->map(fn ($i) => $i->getLabel())->all();
        $this->assertSame(['Dashboard SaaS'], array_values($soltos));

        $item = fn (string $grupo) => collect($grupos->first(fn ($g) => $g->getLabel() === $grupo)?->getItems() ?? [])->map(fn ($i) => $i->getLabel())->all();
        $this->assertContains('Prospects', $item('Prospecção'));
        $this->assertContains('Empresas (Tenants)', $item('Clientes e Contratos'));
        $this->assertContains('Usuários do Sistema', $item('Segurança e Conformidade'));
    }

    public function test_todo_grupo_novo_do_menu_da_central_tem_icone(): void
    {
        $svgs = fn (string $grupo) => substr_count(Blade::render('<x-filament-panels::sidebar.group :label="$label" :items="[]" />', ['label' => $grupo]), '<svg');
        $semIcone = $svgs('Grupo Inexistente');

        foreach (['Prospecção', 'Clientes e Contratos', 'Acessos e Uso', 'Treinamento', 'Site Institucional', 'Segurança e Conformidade', 'Sistema', 'Comercial'] as $grupo) {
            $this->assertGreaterThan($semIcone, $svgs($grupo), "Grupo sem icone: {$grupo}");
        }
    }
}
