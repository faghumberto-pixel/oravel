<?php

namespace Tests\Feature;

use App\Filament\Pages\HistoricoPatrimonio;
use App\Models\AbcMatrix;
use App\Models\AbcMatrixHistory;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lacuna registrada no Histórico do Patrimônio: AbcMatrix era um snapshot
 * único por Ativo, sem trilha de mudança de nível. AbcMatrixObserver
 * (registrado em AppServiceProvider) passa a gravar cada create/update em
 * AbcMatrixHistory.
 */
class AbcMatrixHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano ABC History '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_abc_matrix'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant ABC History '.uniqid(), 'slug' => 'tenant-abc-history-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_creating_abc_matrix_logs_initial_level_with_no_previous_level(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste ABC', 'status' => Asset::STATUS_DISPONIVEL]);

        AbcMatrix::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'nivel' => 'A', 'descricao' => 'Essencial']);

        $history = AbcMatrixHistory::where('asset_id', $asset->id)->sole();
        $this->assertNull($history->nivel_anterior);
        $this->assertSame('A', $history->nivel_novo);
        $this->assertSame($admin->id, $history->changed_by_user_id);
    }

    public function test_updating_the_level_logs_previous_and_new_level(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste ABC 2', 'status' => Asset::STATUS_DISPONIVEL]);
        $matrix = AbcMatrix::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'nivel' => 'B', 'descricao' => 'Importante']);

        $matrix->update(['nivel' => 'A']);

        $this->assertSame(2, AbcMatrixHistory::where('asset_id', $asset->id)->count());

        $ultima = AbcMatrixHistory::where('asset_id', $asset->id)->where('nivel_novo', 'A')->sole();
        $this->assertSame('B', $ultima->nivel_anterior);
    }

    public function test_updating_without_changing_the_level_does_not_log_anything(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste ABC 3', 'status' => Asset::STATUS_DISPONIVEL]);
        $matrix = AbcMatrix::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'nivel' => 'C', 'descricao' => 'Desejável']);

        $matrix->update(['descricao' => 'Descrição atualizada, nível igual']);

        $this->assertSame(1, AbcMatrixHistory::where('asset_id', $asset->id)->count());
    }

    public function test_historico_patrimonio_surfaces_level_changes_as_criticidade_events(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Histórico ABC', 'status' => Asset::STATUS_DISPONIVEL]);
        $matrix = AbcMatrix::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'nivel' => 'B', 'descricao' => 'Importante']);
        $matrix->update(['nivel' => 'A']);

        $page = new HistoricoPatrimonio;
        $page->mount($asset->id);

        $events = $page->getAllEvents();
        $criticidade = $events->filter(fn ($e) => in_array('criticidade', $e['tipos'], true));

        $this->assertCount(2, $criticidade);
        $this->assertTrue($criticidade->contains('title', 'Nível ABC alterado: — → B'));
        $this->assertTrue($criticidade->contains('title', 'Nível ABC alterado: B → A'));
    }
}
