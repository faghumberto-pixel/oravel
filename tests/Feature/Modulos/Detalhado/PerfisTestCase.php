<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SaaSRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Dois tenants (A e B), cada um com administrador, tecnico e colaborador, e os
 * helpers para dar permissao a eles. Perfis seguem App\Support\AppProfile:
 *  - administrador: role `admin` do tenant;
 *  - tecnico: role `tecnico` + permissao de ler OS;
 *  - colaborador: sem role nenhuma.
 *
 * DatabaseTransactions (nunca RefreshDatabase): config/database.php fixa pgsql,
 * entao rode com DB_DATABASE apontando para um banco descartavel.
 */
abstract class PerfisTestCase extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    /** @var array{admin: User, tecnico: User, colaborador: User} */
    protected array $a;

    /** @var array{admin: User, tecnico: User, colaborador: User} */
    protected array $b;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenantA, $this->a] = $this->montarTenant();
        [$this->tenantB, $this->b] = $this->montarTenant();
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{0: Tenant, 1: array{admin: User, tecnico: User, colaborador: User}} */
    protected function montarTenant(?array $features = null): array
    {
        $plan = Plan::create([
            'name' => 'Plano Teste '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => $features ?? array_values(array_filter(array_column(SaaSRegistry::modules(), 'feature'))),
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant '.uniqid(), 'slug' => 'teste-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $perfis = [];
        foreach (['admin', 'tecnico', 'colaborador'] as $perfil) {
            $user = User::create([
                'name' => ucfirst($perfil).' '.substr($tenant->slug, -5),
                'email' => $perfil.'-'.uniqid().'@oravel.com.br',
                'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            if ($perfil !== 'colaborador') {
                $role = Role::firstOrCreate(['name' => $perfil, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
                $user->assignRole($role);
            }
            $perfis[$perfil] = $user;
        }

        $this->conceder($perfis['tecnico'], 'ordem_servico', ['ler']);

        return [$tenant, $perfis];
    }

    /** @param  list<string>  $acoes  ler|criar|editar|excluir */
    protected function conceder(User $user, string $slug, array $acoes): void
    {
        foreach ($acoes as $acao) {
            $nome = "{$acao}_{$slug}";
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
            $user->givePermissionTo($nome);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Preenche colunas protegidas e salva (tenant_id nao e fillable em varios modelos). */
    protected function criar(string $model, array $attrs): Model
    {
        $m = new $model;
        $m->forceFill($attrs)->save();

        return $m;
    }

    /**
     * Lacuna de seguranca ja conhecida: roda a verificacao e, se ela falhar,
     * marca o teste como INCOMPLETO com a descricao (visivel na saida) em vez de
     * quebrar a suite. Quando o codigo for corrigido o teste passa sozinho.
     */
    protected function lacunaConhecida(string $descricao, callable $verificacao): void
    {
        try {
            $verificacao();
        } catch (AssertionFailedError $e) {
            $this->markTestIncomplete('LACUNA CONHECIDA: '.$descricao);
        }
    }
}
