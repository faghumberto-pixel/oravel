<?php

namespace Tests\Feature;

use App\Models\Checklist;
use App\Models\Material;
use App\Models\User;
use App\Policies\ChecklistPolicy;
use App\Policies\MaterialPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Policies feitas a mao chamavam hasPermissionTo(), que LANCA
 * PermissionDoesNotExist quando a Permission ainda nao foi criada (o que acontece
 * ate alguem abrir "Perfis de Acesso" no tenant) e derrubava ate o menu do painel
 * com 500. Agora deve negar de forma limpa.
 */
class PolicyPermissaoAusenteTest extends TestCase
{
    use DatabaseTransactions;

    private function usuarioSemPapel(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_approved' => true])->save();

        return $user;
    }

    public function test_material_policy_nega_sem_lancar_quando_a_permissao_nao_existe(): void
    {
        $this->assertNull(Permission::where('name', 'ler_material')->first());
        $user = $this->usuarioSemPapel();
        $policy = new MaterialPolicy;

        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->view($user, new Material));
        $this->assertFalse($policy->update($user, new Material));
        $this->assertFalse($policy->delete($user, new Material));
    }

    public function test_checklist_policy_nega_sem_lancar_quando_a_permissao_nao_existe(): void
    {
        $this->assertNull(Permission::where('name', 'ler_checklist')->first());
        $user = $this->usuarioSemPapel();
        $policy = new ChecklistPolicy;

        $this->assertFalse($policy->viewAny($user));
        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->view($user, new Checklist));
        $this->assertFalse($policy->update($user, new Checklist));
        $this->assertFalse($policy->delete($user, new Checklist));
    }

    public function test_com_a_permissao_concedida_continua_liberando(): void
    {
        Permission::firstOrCreate(['name' => 'ler_material', 'guard_name' => 'web']);
        $user = $this->usuarioSemPapel();
        $user->givePermissionTo('ler_material');

        $policy = new MaterialPolicy;

        $this->assertTrue($policy->viewAny($user));
        $this->assertFalse($policy->delete($user, new Material));
    }
}
