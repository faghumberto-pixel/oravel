<?php

namespace Tests\Feature;

use App\Livewire\DatabaseNotifications;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantTimeline;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Avisos de pagamento/assinatura só aparecem no sino da Central, nunca no app. */
class CentralNotificationsScopeTest extends TestCase
{
    use RefreshDatabase;

    private function superAdminOfATenant(): array
    {
        $plan = Plan::create(['name' => 'Plano Sino', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $own = Tenant::create(['name' => 'Oravel Teste', 'slug' => 'oravel-sino-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => $own->id]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        return [$super, $plan];
    }

    private function seedNotifications(User $user): void
    {
        Notification::make()->title('Aviso do app')->sendToDatabase($user);
        Notification::make()->title('Mensalidade paga')->viewData(['scope' => 'central'])->sendToDatabase($user);
        // aviso antigo, sem a marca, mas com link para a central
        Notification::make()->title('Contrato assinado antigo')->actions([Action::make('open')->url('https://app.oravel.com.br/central/tenants/abc/edit')])->sendToDatabase($user);
    }

    private function titlesIn(string $panel, User $user): array
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel($panel));

        return Livewire::test('database-notifications')->instance()->getNotificationsQuery()->get()
            ->map(fn ($n) => $n->data['title'])->sort()->values()->all();
    }

    public function test_app_bell_hides_payment_notices_and_central_bell_shows_them(): void
    {
        [$super] = $this->superAdminOfATenant();
        $this->seedNotifications($super);

        $this->assertSame(['Aviso do app'], $this->titlesIn('admin', $super));
        $this->assertSame(['Aviso do app', 'Contrato assinado antigo', 'Mensalidade paga'], $this->titlesIn('central', $super));
    }

    public function test_unread_badge_in_the_app_ignores_central_notices(): void
    {
        [$super] = $this->superAdminOfATenant();
        $this->seedNotifications($super);
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertSame(1, Livewire::test('database-notifications')->instance()->getUnreadNotificationsCount());
    }

    public function test_the_bell_still_never_deletes_notifications(): void
    {
        [$super] = $this->superAdminOfATenant();
        $this->seedNotifications($super);
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $bell = Livewire::test('database-notifications');
        $this->assertInstanceOf(DatabaseNotifications::class, $bell->instance());

        // Auditoria: o sino não apaga (comportamento que já existia e não pode mudar).
        $bell->call('clearNotifications');
        $this->assertSame(3, $super->notifications()->count());
    }

    public function test_notices_sent_by_the_timeline_carry_the_central_scope(): void
    {
        [$super, $plan] = $this->superAdminOfATenant();
        $client = Tenant::create(['name' => 'Cliente Sino', 'slug' => 'cliente-sino-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'trial']);

        TenantTimeline::record($client, 'anotacao', 'Mensalidade paga', 'R$ 600', [], null, true, 'success');

        $notice = $super->notifications()->get()->first(fn ($n) => ($n->data['title'] ?? '') === 'Mensalidade paga');
        $this->assertNotNull($notice);
        $this->assertSame('central', $notice->data['viewData']['scope']);
        $this->assertSame([], $this->titlesIn('admin', $super));
    }
}
