<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Central\Resources\TenantResource\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class TenantInterfaceSimplesCentralTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    public function test_central_ativa_interface_simples_na_empresa_e_a_lista_mostra(): void
    {
        $u = User::factory()->create(['email' => 'equipe@oravel.test']);
        $t = Tenant::create(['name' => 'Empresa Simples Teste', 'slug' => 'simples-'.uniqid(), 'status' => 'trial']);

        Livewire::actingAs($u)->test(EditTenant::class, ['record' => $t->getRouteKey()])
            ->assertFormFieldExists('interface_simples')
            ->fillForm(['interface_simples' => 'simples'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue((bool) $t->fresh()->interface_simples);
        $this->assertTrue($t->fresh()->interfaceSimples());

        Livewire::actingAs($u)->test(ListTenants::class)
            ->assertTableColumnStateSet('interface', 'Simples', record: $t->fresh())
            ->filterTable('interface', 'simples')
            ->assertCanSeeTableRecords([$t]);
    }
}
