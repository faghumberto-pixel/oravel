<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** O sino do super admin (tenant Oravel) é o da Central: no app ele fica vazio (05/10/2026). */
class SuperAdminBellIsCentralOnlyTest extends TestCase
{
    use DatabaseTransactions;

    private function bell(): array
    {
        return Livewire::test('database-notifications')->instance()->getNotificationsQuery()->get()->map(fn ($n) => $n->data['title'])->all();
    }

    private function addNotice(User $user, string $title): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'Filament\\Notifications\\DatabaseNotification',
            'notifiable_type' => User::class, 'notifiable_id' => $user->id,
            'data' => json_encode(['format' => 'filament', 'title' => $title, 'viewData' => []]), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_super_admin_bell_is_empty_in_the_app_and_full_in_central_while_regular_users_keep_theirs(): void
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $email = 'dono-'.uniqid().'@oravel.test';
        config(['oravel.super_admins' => [$email]]);
        $super = User::create(['name' => 'Dono', 'email' => $email, 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $regular = User::create(['name' => 'Func', 'email' => 'f-'.uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $this->addNotice($super, 'Novo lead do site');
        $this->addNotice($regular, 'Conta vencendo');

        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        session(['acting_tenant_id' => $tenant->id]);
        $this->assertSame([], $this->bell());

        Filament::setCurrentPanel(Filament::getPanel('central'));
        $this->assertSame(['Novo lead do site'], $this->bell());

        $this->actingAs($regular);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->assertSame(['Conta vencendo'], $this->bell());
    }
}
