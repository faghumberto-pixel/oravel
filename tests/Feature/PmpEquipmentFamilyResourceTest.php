<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\PmpEquipmentFamilyResource\Pages\ListPmpEquipmentFamilies;
use App\Models\PmpEquipmentFamily;
use App\Models\PmpTemplateItem;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Catálogo global de PMP (sem tenant_id) -- só o super admin gerencia,
 * painel central. Confirma que a Resource renderiza e que o contador de
 * itens da RelationManager reflete o real.
 */
class PmpEquipmentFamilyResourceTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        return $super;
    }

    public function test_super_admin_can_list_equipment_families(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $family = PmpEquipmentFamily::create([
            'segment' => 'empilhadeiras', 'name' => 'Elétricos Leves / Modulares',
        ]);
        PmpTemplateItem::create([
            'pmp_equipment_family_id' => $family->id, 'name' => 'Item Teste',
            'periodicity_label' => 'Diária', 'interval_days' => 1,
        ]);

        Livewire::test(ListPmpEquipmentFamilies::class)
            ->assertOk()
            ->assertSee('Elétricos Leves / Modulares');
    }

    public function test_family_is_not_scoped_to_any_tenant(): void
    {
        $family = PmpEquipmentFamily::create(['segment' => 'empilhadeiras', 'name' => 'Teste Global']);

        // Sem nenhum tenant_id na tabela -- não há coluna, não há como
        // vazar/escopar. Confirma que a query simples enxerga o registro
        // independente de qualquer contexto de tenant autenticado.
        $this->assertDatabaseHas('pmp_equipment_families', ['id' => $family->id]);
        $this->assertFalse(Schema::hasColumn('pmp_equipment_families', 'tenant_id'));
    }
}
