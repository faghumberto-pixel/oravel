<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\HubMenus;
use App\Support\SaaSRegistry;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Páginas de menu pai mostram um cartão com ícone para cada menu filho, em vez de uma frase vazia. */
class HubMenusFilhosTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(array $desligar = []): User
    {
        $features = [];
        foreach (SaaSRegistry::modules() as $m) {
            if ($m['feature']) {
                $features[$m['feature']] = true;
            }
        }
        foreach (array_keys(config('menu_modules')) as $k) {
            $features[$k] = true;
        }
        foreach ($desligar as $k) {
            $features[$k] = false;
        }

        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@t.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]));

        return $admin;
    }

    /** @return array<string, list<string>> url do menu pai => rótulos dos filhos que o admin enxerga */
    private function paisComFilhos(): array
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app()->forgetInstance(NavigationManager::class);

        $mapa = [];
        foreach (Filament::getNavigation() as $grupo) {
            foreach ($grupo->getItems() as $item) {
                if (count($item->getChildItems())) {
                    $mapa[$item->getUrl()] = collect($item->getChildItems())->map->getLabel()->all();
                }
            }
        }

        return $mapa;
    }

    public function test_every_parent_page_lists_all_its_children_as_cards_with_links(): void
    {
        $this->actingAs($this->admin());
        $pais = $this->paisComFilhos();

        // Páginas de menu pai: as que só renderizam o componente dos cartões.
        $hubs = collect(Filament::getCurrentPanel()->getPages())
            ->filter(fn ($classe) => is_string((new \ReflectionClass($classe))->getDefaultProperties()['view'] ?? null)
                && trim((string) @file_get_contents(view((new \ReflectionClass($classe))->getDefaultProperties()['view'])->getPath())) === '<x-hub-menu-filhos />')
            ->values();

        $this->assertGreaterThanOrEqual(15, $hubs->count());

        $comFilhos = 0;
        foreach ($hubs as $classe) {
            $url = $classe::getUrl();
            $filhos = $pais[$url] ?? [];
            $resposta = $this->get($url)->assertOk()->assertDontSee('Selecione uma opção no menu para começar');

            if ($filhos === []) {
                continue;
            }

            $comFilhos++;
            $resposta->assertSee('hub-h1', false)->assertSee(e(HubMenus::descricao($classe)), false)->assertDontSee('Escolha uma das opções abaixo para começar.');
            $resposta->assertSee('hub-card', false);
            foreach ($filhos as $rotulo) {
                $resposta->assertSee(e($rotulo), false);
            }
        }

        $this->assertGreaterThanOrEqual(10, $comFilhos);
    }

    public function test_children_the_contract_does_not_include_do_not_appear_on_the_parent_page(): void
    {
        $this->actingAs($this->admin(['tabela_asset_categories']));
        $url = collect($this->paisComFilhos())->filter(fn ($filhos) => in_array('Grupos de Ativos', $filhos, true))->keys()->first();
        $this->assertNotNull($url);

        $this->get($url)->assertOk()->assertSee('Grupos de Ativos')->assertDontSee('Categorias de Ativos');
    }
}
