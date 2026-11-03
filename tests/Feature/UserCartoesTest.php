<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Lista de Colaboradores em cartoes (padrao CRM Comercial), sem cards no topo, com cards de totais (pedido 2026-11-03). */
class UserCartoesTest extends TestCase
{
    use DatabaseTransactions;

    private function cenario(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Users '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_users'],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'usr-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin Cartao', 'email' => 'adm-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true, 'role' => 'admin', 'last_seen' => now()]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return [$tenant, $admin];
    }

    private function usuario(Tenant $t, string $nome, array $o = []): User
    {
        return User::create($o + ['name' => $nome, 'email' => strtolower(str_replace(' ', '', $nome)).uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true, 'role' => 'colaborador']);
    }

    public function test_colaboradores_em_cartoes_sem_cards_antigos_e_cards_filtram(): void
    {
        [$t, $admin] = $this->cenario();
        $tec = $this->usuario($t, 'Tecnico Um', ['role' => 'tecnico', 'last_seen' => now()->subDays(30)]);
        $pend = $this->usuario($t, 'Pendente Dois', ['is_approved' => false]);

        $this->assertSame([], (new \ReflectionMethod(ListUsers::class, 'getHeaderWidgets'))->invoke(new ListUsers));

        $lista = Livewire::test(ListUsers::class)
            ->assertSeeHtml('fi-ta-content-grid')->assertSeeHtml('border-emerald-600')->assertSeeHtml('border-amber-600')->assertSee('Tecnico Um');

        $cards = collect($lista->instance()->cards())->keyBy('chave');
        $this->assertSame(3, $cards['total']['total']);
        $this->assertSame(2, $cards['aprovados']['total']);
        $this->assertSame(1, $cards['pendentes']['total']);
        $this->assertSame(1, $cards['tecnicos']['total']);
        $this->assertSame(1, $cards['admins']['total']);
        $this->assertSame(1, $cards['semana']['total']);

        $lista->call('filtrarCard', 'pendentes')
            ->assertCanSeeTableRecords([$pend])->assertCanNotSeeTableRecords([$admin, $tec])
            ->call('filtrarCard', 'tecnicos')
            ->assertCanSeeTableRecords([$tec])->assertCanNotSeeTableRecords([$admin, $pend])
            ->call('filtrarCard', 'semana')
            ->assertCanSeeTableRecords([$admin])->assertCanNotSeeTableRecords([$tec])
            ->call('filtrarCard', 'total')
            ->assertCanSeeTableRecords([$admin, $tec, $pend]);
    }
}
