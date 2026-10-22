<?php

namespace Tests\Feature\Modulos;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SaaSRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;
use Throwable;

/**
 * Smoke de TODO Resource do painel admin: um admin de tenant com plano que
 * inclui todos os modulos precisa conseguir abrir a listagem e o formulario de
 * criacao sem excecao. Pega o tipo de bug mais comum (coluna/relacao/classe
 * inexistente na tabela ou no form) em um modulo que ninguem abre ha tempo.
 *
 * DatabaseTransactions (nunca RefreshDatabase): o config/database.php fixa
 * pgsql, entao rode com DB_DATABASE apontando para um banco descartavel.
 */
class ModulosRenderizamTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Resources que ja falhavam ao abrir quando este smoke foi criado
     * (2026-10-22). Cada item e um bug real a corrigir; a lista so pode
     * encolher e o teste reclama se um item passar a funcionar.
     */
    private const LISTAGEM_QUEBRADA_CONHECIDA = [];

    private const CRIACAO_QUEBRADA_CONHECIDA = [];

    private function adminDeTenantComTodosOsModulos(): User
    {
        $plan = Plan::create([
            'name' => 'Plano Smoke '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => array_values(array_filter(array_column(SaaSRegistry::modules(), 'feature'))),
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Smoke '.uniqid(), 'slug' => 'smoke-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return $admin;
    }

    /** @return array<string, class-string> nome curto => classe do Resource */
    private function resources(): array
    {
        $out = [];
        foreach (glob(app_path('Filament/Resources/*Resource.php')) as $file) {
            $class = 'App\\Filament\\Resources\\'.basename($file, '.php');
            if (! class_exists($class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            $out[basename($file, 'Resource.php')] = $class;
        }

        return $out;
    }

    private function paginaDe(string $resource, string $chave): ?string
    {
        $page = $resource::getPages()[$chave] ?? null;

        return $page ? $page->getPage() : null;
    }

    public function test_listagem_de_todo_resource_abre(): void
    {
        $this->actingAs($this->adminDeTenantComTodosOsModulos());

        $falhas = [];
        $total = 0;
        foreach ($this->resources() as $nome => $resource) {
            $pagina = $this->paginaDe($resource, 'index');
            if (! $pagina) {
                continue;
            }
            $total++;
            $conhecido = in_array($nome, self::LISTAGEM_QUEBRADA_CONHECIDA, true);
            try {
                Livewire::test($pagina); // excecao = falha; redirect de policy (ex: Course) e aceitavel
                if ($conhecido) {
                    $falhas[] = "{$nome}: listagem voltou a funcionar, remova de LISTAGEM_QUEBRADA_CONHECIDA";
                }
            } catch (Throwable $e) {
                if (! $conhecido) {
                    $falhas[] = "{$nome}: ".get_class($e).' - '.strtok($e->getMessage(), "\n");
                }
            }
        }

        $this->assertGreaterThan(50, $total, 'descoberta de Resources encontrou poucos');
        $this->assertSame([], $falhas);
    }

    public function test_formulario_de_criacao_de_todo_resource_abre(): void
    {
        $this->actingAs($this->adminDeTenantComTodosOsModulos());

        $falhas = [];
        foreach ($this->resources() as $nome => $resource) {
            $pagina = $this->paginaDe($resource, 'create');
            if (! $pagina) {
                continue;
            }
            $conhecido = in_array($nome, self::CRIACAO_QUEBRADA_CONHECIDA, true);
            try {
                Livewire::test($pagina); // excecao = falha; redirect de policy (ex: Course) e aceitavel
                if ($conhecido) {
                    $falhas[] = "{$nome}: criacao voltou a funcionar, remova de CRIACAO_QUEBRADA_CONHECIDA";
                }
            } catch (Throwable $e) {
                if (! $conhecido) {
                    $falhas[] = "{$nome}: ".get_class($e).' - '.strtok($e->getMessage(), "\n");
                }
            }
        }

        $this->assertSame([], $falhas);
    }
}
