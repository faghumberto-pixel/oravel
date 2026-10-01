<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\DatabaseBackupResource\Pages\ListDatabaseBackups;
use App\Filament\Central\Widgets\BackupStatsOverview;
use App\Models\DatabaseBackup;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantBackupService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Backups separados por cliente: o que cada arquivo contém e a tela da central agrupada por cliente. */
class TenantBackupTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $name): Tenant
    {
        $plan = Plan::firstOrCreate(['name' => 'Plano Backup'], ['price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        return Tenant::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function actAsSuperAdmin(): void
    {
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => null]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    public function test_classification_puts_platform_tables_outside_the_tenant_files(): void
    {
        $class = app(TenantBackupService::class)->classify();

        $this->assertContains('assets', $class['tenant']);
        $this->assertContains('users', $class['tenant']);
        $this->assertContains('maintenance_order_material', $class['children']);
        // tabelas que apontam para users/tenants mas são da PLATAFORMA (bug visto na prova de cobertura)
        foreach (['announcements', 'blocked_ips', 'plans', 'permissions', 'sales_leads', 'web_visits'] as $platform) {
            $this->assertContains($platform, $class['platform'], "{$platform} deve ir no arquivo da plataforma");
        }
        $this->assertNotContains('sessions', array_merge($class['tenant'], $class['children'], $class['platform']));
    }

    public function test_a_tenant_copy_has_only_that_tenants_rows(): void
    {
        $a = $this->tenant('Cliente A');
        $b = $this->tenant('Cliente B');
        $userA = User::create(['name' => 'A1', 'email' => 'a1@a.com', 'password' => 'x12345678', 'tenant_id' => $a->id, 'role' => 'admin', 'hourly_rate' => 0]);
        User::create(['name' => 'B1', 'email' => 'b1@b.com', 'password' => 'x12345678', 'tenant_id' => $b->id, 'role' => 'admin', 'hourly_rate' => 0]);

        $service = app(TenantBackupService::class);
        $counts = $service->materializeTenant($a->id, 'bk_teste_a');

        try {
            $this->assertSame(1, $counts['tenants']);
            $this->assertSame(1, $counts['users']);
            $this->assertSame([$userA->id], \DB::table('bk_teste_a.users')->pluck('id')->all());
            $this->assertSame([$a->id], \DB::table('bk_teste_a.tenants')->pluck('id')->all());
        } finally {
            \DB::statement('drop schema if exists bk_teste_a cascade');
        }
    }

    public function test_central_screen_is_grouped_by_client_with_a_summary(): void
    {
        $this->actAsSuperAdmin();
        $a = $this->tenant('Topmixx');
        $b = $this->tenant('Locação Silva');

        $mk = fn (?Tenant $t, string $kind, string $label, array $extra = []) => DatabaseBackup::create($extra + [
            'tenant_id' => $t?->id, 'kind' => $kind, 'client_label' => $label, 'filename' => "{$label}.sql.gz", 'path' => "/x/{$label}.sql.gz",
            'size_bytes' => 2048, 'rows_count' => 10, 'tenant_count' => $t ? 1 : 0, 'tenant_names' => $t ? [$t->name] : [], 'status' => DatabaseBackup::STATUS_COMPLETED,
        ]);
        $top = $mk($a, DatabaseBackup::KIND_TENANT, 'Topmixx');
        $mk($b, DatabaseBackup::KIND_TENANT, 'Locação Silva')->forceFill(['created_at' => now()->subDays(3)])->save();
        $mk(null, DatabaseBackup::KIND_PLATFORM, 'Plataforma');

        Livewire::test(ListDatabaseBackups::class)
            ->assertSuccessful()
            ->assertSee('Topmixx')
            ->assertSee('Locação Silva')
            ->assertSee('Plataforma')
            ->assertCanSeeTableRecords([$top]);

        // Resumo: só a Topmixx tem backup das últimas 26 h; a Locação Silva (3 dias) aparece como sem backup recente.
        Livewire::test(BackupStatsOverview::class)
            ->assertSee('1 de 2')
            ->assertSee('Locação Silva');
    }

    public function test_backups_widget_is_registered_in_the_central_panel(): void
    {
        $this->assertContains(BackupStatsOverview::class, Filament::getPanel('central')->getWidgets());
    }
}
