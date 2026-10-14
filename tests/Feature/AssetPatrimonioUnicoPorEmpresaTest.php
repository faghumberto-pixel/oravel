<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\CreateAsset;
use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O Nº Patrimonio era unico entre TODAS as empresas (unique() sem escopo de
 * tenant): uma locadora nao conseguia usar "PAT-001" se outra ja tivesse.
 * Achado na conferencia das aulas de cadastro (14/10/2026).
 */
class AssetPatrimonioUnicoPorEmpresaTest extends TestCase
{
    use DatabaseTransactions;

    private function empresa(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Patrimonio '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Empresa '.uniqid(), 'slug' => 'empresa-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $user = User::create([
            'name' => 'Admin', 'email' => 'p-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'),
            'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $role = new Role(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $role->save();
        $user->assignRole($role);

        return [$tenant, $user];
    }

    private function ativo(Tenant $tenant, string $patrimonio): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo '.uniqid(), 'tag' => 'T-'.uniqid(),
            'patrimonio' => $patrimonio, 'status' => 'disponivel',
        ]);
    }

    public function test_same_patrimonio_is_blocked_inside_the_same_company(): void
    {
        [$tenant, $user] = $this->empresa();
        $this->ativo($tenant, 'PAT-001');
        $this->actingAs($user);

        Livewire::test(CreateAsset::class)
            ->fillForm(['patrimonio' => 'PAT-001'])
            ->call('create')
            ->assertHasFormErrors(['patrimonio' => 'unique']);
    }

    public function test_same_patrimonio_is_allowed_in_another_company(): void
    {
        [$tenantA] = $this->empresa();
        [$tenantB, $userB] = $this->empresa();
        $this->ativo($tenantA, 'PAT-001');
        $this->actingAs($userB);

        Livewire::test(CreateAsset::class)
            ->fillForm(['patrimonio' => 'PAT-001'])
            ->call('create')
            ->assertHasNoFormErrors(['patrimonio']);
    }

    public function test_editing_an_asset_does_not_conflict_with_itself(): void
    {
        [$tenant, $user] = $this->empresa();
        $ativo = $this->ativo($tenant, 'PAT-001');
        $this->actingAs($user);

        Livewire::test(EditAsset::class, ['record' => $ativo->getKey()])
            ->fillForm(['patrimonio' => 'PAT-001'])
            ->call('save')
            ->assertHasNoFormErrors(['patrimonio']);
    }

    public function test_api_blocks_a_duplicate_only_inside_the_same_company(): void
    {
        [$tenantA, $userA] = $this->empresa();
        [$tenantB, $userB] = $this->empresa();
        $this->ativo($tenantA, 'PAT-001');
        $corpo = ['name' => 'Novo', 'patrimonio' => 'PAT-001', 'status' => 'disponivel'];

        Sanctum::actingAs($userA);
        $this->postJson('/api/assets', $corpo)->assertStatus(422)->assertJsonValidationErrors('patrimonio');

        Sanctum::actingAs($userB);
        $this->postJson('/api/assets', $corpo)->assertCreated();
    }
}
