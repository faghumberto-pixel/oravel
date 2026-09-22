<?php

namespace Tests\Feature;

use App\Filament\Widgets\Nr13Stats;
use App\Models\Asset;
use App\Models\Nr13Document;
use App\Models\Nr13Inspection;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Mesma técnica de tests/Feature/AssetStatsWidgetTest.php (ReflectionMethod pra invocar
 * getStats() protegido).
 *
 * DatabaseTransactions (e NÃO RefreshDatabase): config/database.php fixa 'default' => 'pgsql'.
 */
class Nr13StatsWidgetTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano NR13 Stats '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);
        $tenant = Tenant::create(['name' => 'Tenant '.uniqid(), 'slug' => 'nr13-stats-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'nr13stats-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = new Role(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $role->save();
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function getStats(): array
    {
        $widget = new Nr13Stats;
        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    private function statValue(array $stats, string $label): int
    {
        return collect($stats)->first(fn ($stat) => $stat->getLabel() === $label)->getValue();
    }

    public function test_conta_validos_a_vencer_e_vencidos_cruzando_documentos_e_inspecoes(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Caldeira', 'status' => Asset::STATUS_DISPONIVEL]);

        Nr13Document::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->addDays(90)]); // válido
        Nr13Document::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->addDays(10)]); // a vencer
        Nr13Document::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->subDay()]); // vencido
        Nr13Document::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_PRONTUARIO, 'data_validade' => null]); // sem validade: fora da conta

        Nr13Inspection::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Inspection::TIPO_INTERNA, 'data_inspecao' => now()->subMonth(), 'data_proxima_inspecao' => now()->addDays(90)]); // válida
        Nr13Inspection::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'tipo' => Nr13Inspection::TIPO_EXTERNA, 'data_inspecao' => now()->subYear(), 'data_proxima_inspecao' => now()->subDay()]); // vencida

        $this->actingAs($admin);
        $stats = $this->getStats();

        $this->assertSame(2, $this->statValue($stats, 'Válidos'));
        $this->assertSame(1, $this->statValue($stats, 'A vencer'));
        $this->assertSame(2, $this->statValue($stats, 'Vencidos'));
    }

    public function test_sem_nenhum_registro_todos_os_cards_ficam_em_zero(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $stats = $this->getStats();

        $this->assertSame(0, $this->statValue($stats, 'Válidos'));
        $this->assertSame(0, $this->statValue($stats, 'A vencer'));
        $this->assertSame(0, $this->statValue($stats, 'Vencidos'));
    }
}
