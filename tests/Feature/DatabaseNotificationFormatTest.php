<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\Announcement;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Notifications\ContaPagarNotification;
use App\Notifications\MaintenanceDueNotification;
use App\Notifications\NewInternalEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O sino do Filament (Filament\Livewire\DatabaseNotifications::
 * getNotificationsQuery()) so' mostra notificacao com data->format=
 * 'filament' -- uma Notification::toDatabase() do Laravel puro (sem passar
 * por Notification::make()->sendToDatabase()) fica invisivel sem essa
 * chave, mesmo com a linha certinha gravada no banco. As 4 classes abaixo
 * ja levaram esse bug real (achado 2026-07-27); este teste trava a
 * regressao.
 */
class DatabaseNotificationFormatTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Notif '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);

        return Tenant::create([
            'name' => 'Tenant Notif '.uniqid(), 'slug' => 'tenant-notif-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeUser(?Tenant $tenant = null): User
    {
        $tenant ??= $this->makeTenant();

        $user = User::create([
            'name' => 'Usuário', 'email' => 'notif-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        return $user;
    }

    /**
     * Mesma query que Filament\Livewire\DatabaseNotifications usa pro sino
     * -- reproduzida aqui pra nao depender de montar o componente Livewire
     * inteiro so' pra checar o filtro.
     */
    private function visibleInBell(User $user): int
    {
        return $user->notifications()->where('data->format', 'filament')->count();
    }

    public function test_announcement_notification_is_visible_in_bell(): void
    {
        $user = $this->makeUser();
        $announcement = new Announcement(['title' => 'Aviso', 'message' => 'Corpo', 'level' => Announcement::LEVEL_INFO]);

        $user->notify(new AnnouncementNotification($announcement));

        $this->assertSame(1, $this->visibleInBell($user));
    }

    public function test_conta_pagar_notification_is_visible_in_bell(): void
    {
        $user = $this->makeUser();
        $conta = new AccountPayable(['description' => 'Aluguel', 'amount' => 100, 'due_date' => now(), 'tenant_id' => $user->tenant_id]);

        $user->notify(new ContaPagarNotification($conta, 'lancamento'));

        $this->assertSame(1, $this->visibleInBell($user));
    }

    public function test_maintenance_due_notification_is_visible_in_bell(): void
    {
        $user = $this->makeUser();
        $asset = Asset::create(['tenant_id' => $user->tenant_id, 'name' => 'Gerador', 'patrimonio' => 'PAT-1', 'status' => Asset::STATUS_DISPONIVEL]);

        $user->notify(new MaintenanceDueNotification($asset, ['overdue_hours' => 10, 'due_at_hours' => 100]));

        $this->assertSame(1, $this->visibleInBell($user));
    }

    public function test_new_internal_email_notification_is_visible_in_bell(): void
    {
        $tenant = $this->makeTenant();
        $userA = $this->makeUser($tenant);
        $userB = $this->makeUser($tenant);

        $message = \App\Models\EmailMessage::create([
            'tenant_id' => $tenant->id,
            'from_user_id' => $userA->id,
            'subject' => 'Aviso interno',
            'status' => \App\Models\EmailMessage::STATUS_ENVIADO,
        ]);

        $userB->notify(new NewInternalEmailNotification($message));

        $this->assertSame(1, $this->visibleInBell($userB));
    }
}
