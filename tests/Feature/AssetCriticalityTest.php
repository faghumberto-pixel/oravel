<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource;
use App\Models\AbcMatrix;
use App\Models\Asset;
use App\Models\CriticalityLevel;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCriticalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_criticality_level_resolves_via_abc_matrix_nivel(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Criticidade '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_abc_matrix', 'tabela_criticality_levels'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Criticidade '.uniqid(), 'slug' => 'tenant-criticidade-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel']);

        $this->assertNull($asset->currentCriticalityLevel());

        CriticalityLevel::create(['tenant_id' => $tenant->id, 'code' => 'A', 'name' => 'Alta', 'color' => '#ff0000']);
        AbcMatrix::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'nivel' => 'A', 'descricao' => 'Critica']);

        $asset->refresh();
        $level = $asset->currentCriticalityLevel();

        $this->assertNotNull($level);
        $this->assertSame('Alta', $level->name);
        $this->assertSame('#ff0000', $level->color);
    }

    public function test_asset_resource_table_shows_criticality_column_without_error(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Criticidade2 '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_abc_matrix', 'tabela_criticality_levels'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Criticidade2 '.uniqid(), 'slug' => 'tenant-criticidade2-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);
        $this->actingAs($admin);

        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel']);

        $this->get(AssetResource::getUrl('index'))->assertSuccessful();
    }
}
