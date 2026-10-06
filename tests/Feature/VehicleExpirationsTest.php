<?php

namespace Tests\Feature;

use App\Filament\Resources\VehicleExpirationResource\Pages\ListVehicleExpirations;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\VehicleExpirations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Tela "Vencimento de veículos": seguro, IPVA, licenciamento e tacógrafo (pesados), com abas e filtro (06/10/2026). */
class VehicleExpirationsTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantWithAdmin(): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function vehicle(Tenant $tenant, string $placa, array $extra = []): Asset
    {
        return Asset::create(array_merge([
            'tenant_id' => $tenant->id, 'name' => 'Veículo '.$placa, 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => $placa,
        ], $extra));
    }

    public function test_status_colors_and_descriptions(): void
    {
        $this->assertSame('gray', VehicleExpirations::status(null)[1]);
        $this->assertSame('danger', VehicleExpirations::status(Carbon::now()->subDays(3))[1]);
        $this->assertStringContainsString('venceu há 3', VehicleExpirations::status(Carbon::now()->subDays(3))[2]);
        $this->assertSame('warning', VehicleExpirations::status(Carbon::now()->addDays(10))[1]);
        $this->assertSame('info', VehicleExpirations::status(Carbon::now()->addDays(60))[1]);
        $this->assertSame('success', VehicleExpirations::status(Carbon::now()->addDays(200))[1]);
    }

    public function test_apply_filters_by_situation_and_document_and_only_counts_tachograph_for_heavy_vehicles(): void
    {
        [$tenant] = $this->tenantWithAdmin();
        $seguroVencido = $this->vehicle($tenant, 'AAA1A11', ['seguro_vencimento' => now()->subDays(5)]);
        $ipvaEm10 = $this->vehicle($tenant, 'BBB2B22', ['ipva_vencimento' => now()->addDays(10)]);
        $licEm200 = $this->vehicle($tenant, 'CCC3C33', ['licenciamento_vencimento' => now()->addDays(200)]);
        $tacoPesado = $this->vehicle($tenant, 'DDD4D44', ['veiculo_pesado' => true, 'tacografo_vencimento' => now()->subDays(1)]);
        $tacoLeve = $this->vehicle($tenant, 'EEE5E55', ['veiculo_pesado' => false, 'tacografo_vencimento' => now()->subDays(1)]);

        $ids = fn (?string $doc, ?string $sit) => Asset::query()->tap(fn ($q) => VehicleExpirations::apply($q, $doc, $sit))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$seguroVencido->id, $tacoPesado->id], $ids(null, 'vencido'));
        $this->assertSame([$ipvaEm10->id], $ids(null, 'ate_30'));
        $this->assertSame([$ipvaEm10->id], $ids('ipva_vencimento', 'ate_30'));
        $this->assertSame([], $ids('seguro_vencimento', 'ate_30'));
        $this->assertSame([$tacoPesado->id], $ids('tacografo_vencimento', 'vencido'));
        $this->assertContains($licEm200->id, $ids('seguro_vencimento', 'sem_data'));   // sem seguro cadastrado
        $this->assertNotContains($seguroVencido->id, $ids('seguro_vencimento', 'sem_data'));
        $this->assertSame([$ipvaEm10->id], $ids('ipva_vencimento', null));
        $this->assertNotContains($tacoLeve->id, $ids(null, 'vencido'));
    }

    public function test_screen_lists_only_vehicles_of_the_tenant_with_tabs_and_document_filter(): void
    {
        [$tenant, $admin] = $this->tenantWithAdmin();
        [$other] = $this->tenantWithAdmin();
        $vencido = $this->vehicle($tenant, 'AAA1A11', ['seguro_vencimento' => now()->subDays(5)]);
        $ipva = $this->vehicle($tenant, 'BBB2B22', ['ipva_vencimento' => now()->addDays(10)]);
        $maquina = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira', 'tag' => 'M-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL, 'seguro_vencimento' => now()->subDays(9)]);
        $alheio = $this->vehicle($other, 'ZZZ9Z99', ['seguro_vencimento' => now()->subDays(2)]);

        $this->actingAs($admin);

        Livewire::test(ListVehicleExpirations::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$vencido, $ipva])
            ->assertCanNotSeeTableRecords([$maquina, $alheio])
            ->set('activeTab', 'vencido')
            ->assertCanSeeTableRecords([$vencido])
            ->assertCanNotSeeTableRecords([$ipva])
            ->set('activeTab', 'ate_30')
            ->assertCanSeeTableRecords([$ipva])
            ->assertCanNotSeeTableRecords([$vencido]);
    }
}
