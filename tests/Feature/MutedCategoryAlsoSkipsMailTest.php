<?php

namespace Tests\Feature;

use App\Listeners\SkipMutedNotification;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

/** Categoria desligada em "Minhas notificações" corta o sino E o e-mail do mesmo aviso. */
class MutedCategoryAlsoSkipsMailTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $extra = []): User
    {
        $plan = Plan::create(['name' => 'Plano Mute '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $tenant = Tenant::create(['name' => 'Empresa Mute', 'slug' => 'mute-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create(['name' => 'Pessoa', 'email' => 'mute-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true] + $extra)->save();

        return $user->refresh();
    }

    public function test_categoria_desligada_tambem_corta_o_email_do_mesmo_aviso(): void
    {
        $aviso = new class extends Notification
        {
            public function via($n): array
            {
                return ['database', 'mail'];
            }

            public function toDatabase($n): array
            {
                return ['title' => 'Preventiva vencida: Gerador 1'];
            }
        };
        $listener = app(SkipMutedNotification::class);

        $silenciado = $this->user(['muted_notification_categories' => ['ordens_servico']]);
        $this->assertFalse($listener->avaliar(new NotificationSending($silenciado, $aviso, 'mail')));
        $this->assertFalse($listener->avaliar(new NotificationSending($silenciado, $aviso, 'database')));

        $normal = $this->user(['muted_notification_categories' => ['estoque']]);
        $this->assertNull($listener->avaliar(new NotificationSending($normal, $aviso, 'mail')));

        $this->assertNull($listener->avaliar(new NotificationSending($silenciado, $aviso, 'broadcast')));
    }
}
