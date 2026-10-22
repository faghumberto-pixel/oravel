<?php

namespace Tests\Feature;

use App\Filament\Pages\MaintenanceOrderListPage;
use App\Livewire\AssetDemobilizationWizard;
use App\Livewire\AssetMobilizationWizard;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SaaSRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Achados em 2026-10-22 abrindo as telas de campo em tamanho de celular:
 *  - Mobilizacao/Desmobilizacao davam 500 sem movimentacao: o Blade le
 *    `@livewire:navigating="..."` como a diretiva @livewire(...) e chama o
 *    closure sem argumento (ArgumentCountError).
 *  - A lista de OS usava `*-gray-*`, que no preset do Filament vira
 *    rgba(var(--gray-N)); essas variaveis so existem dentro do painel, entao
 *    no layout checklist-mobile o texto herdava a cor clara do body.
 *
 * DatabaseTransactions: rode com DB_DATABASE descartavel (config fixa pgsql).
 */
class TelasMobileRegressaoTest extends TestCase
{
    use DatabaseTransactions;

    private function adminDeTenant(): User
    {
        $plan = Plan::create([
            'name' => 'Plano Mobile '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => array_values(array_filter(array_column(SaaSRegistry::modules(), 'feature'))),
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Mobile '.uniqid(), 'slug' => 'mobile-'.uniqid(),
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

    public function test_wizard_de_mobilizacao_abre_sem_movimentacao(): void
    {
        $this->actingAs($this->adminDeTenant());

        Livewire::test(AssetMobilizationWizard::class)->assertSuccessful()->assertSee('MOBILIZAÇÃO');
    }

    public function test_wizard_de_desmobilizacao_abre_sem_movimentacao(): void
    {
        $this->actingAs($this->adminDeTenant());

        Livewire::test(AssetDemobilizationWizard::class)->assertSuccessful();
    }

    public function test_views_dos_wizards_nao_usam_arroba_livewire_como_atributo(): void
    {
        foreach (['asset-mobilization-wizard', 'asset-demobilization-wizard'] as $view) {
            $this->assertStringNotContainsString(
                '@livewire:',
                file_get_contents(resource_path("views/livewire/{$view}.blade.php")),
                "{$view}: use x-on:livewire:... (o Blade le @livewire como diretiva)"
            );
        }
    }

    public function test_lista_de_os_nao_depende_das_variaveis_gray_do_filament(): void
    {
        $this->actingAs($this->adminDeTenant());

        Livewire::test(MaintenanceOrderListPage::class)->assertSuccessful();

        $this->assertDoesNotMatchRegularExpression(
            '/\b(?:bg|text|border|placeholder)-gray-\d+/',
            file_get_contents(resource_path('views/filament/admin/pages/maintenance-order-list.blade.php')),
            'layout checklist-mobile nao carrega as variaveis --gray-N do Filament: use zinc-*'
        );
    }
}
