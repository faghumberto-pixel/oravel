<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PushSubscription;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Push\PushSender;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Notificações no app do colaborador: sino, push no celular e respeito às preferências. */
class AppColaboradorPushTest extends TestCase
{
    use DatabaseTransactions;

    private const CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private function usuario(array $extra = []): User
    {
        $plan = Plan::create(['name' => 'Plano Push '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['modulo_app_colaborador']]);
        $tenant = Tenant::create(['name' => 'Locadora Push', 'slug' => 'push-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create(['name' => 'Ana', 'email' => 'push-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $user->forceFill(array_merge(['email_verified_at' => now(), 'is_approved' => true], $extra))->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return $user->refresh();
    }

    private function celular(): self
    {
        return $this->withHeaders(['User-Agent' => self::CELULAR]);
    }

    private function aparelho(User $user, string $endpoint = 'https://push.example/abc'): PushSubscription
    {
        return PushSubscription::withoutGlobalScopes()->create([
            'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint), 'p256dh' => 'p', 'auth' => 'a',
        ]);
    }

    private function sender(?bool $resultado = true): object
    {
        $fake = new class($resultado) implements PushSender
        {
            public array $enviados = [];

            public function __construct(private ?bool $resultado) {}

            public function send(array $subscription, string $payload): ?bool
            {
                $this->enviados[] = json_decode($payload, true);

                return $this->resultado;
            }
        };
        $this->app->instance(PushSender::class, $fake);

        return $fake;
    }

    public function test_aparelho_se_inscreve_e_repetir_nao_duplica(): void
    {
        $user = $this->usuario();
        $corpo = ['endpoint' => 'https://push.example/xyz', 'keys' => ['p256dh' => 'k1', 'auth' => 'k2']];

        $this->celular()->postJson('/colaborador/push/assinar', $corpo)->assertUnauthorized();

        $this->celular()->actingAs($user)->postJson('/colaborador/push/assinar', $corpo)->assertOk();
        $this->celular()->actingAs($user)->postJson('/colaborador/push/assinar', $corpo)->assertOk();
        $this->assertSame(1, PushSubscription::withoutGlobalScopes()->where('user_id', $user->id)->count());

        $this->celular()->actingAs($user)->postJson('/colaborador/push/cancelar', ['endpoint' => 'https://push.example/xyz'])->assertOk();
        $this->assertSame(0, PushSubscription::withoutGlobalScopes()->where('user_id', $user->id)->count());
    }

    public function test_aviso_do_sino_vira_push_no_celular(): void
    {
        $user = $this->usuario();
        $this->aparelho($user);
        $fake = $this->sender();

        Notification::make()->title('Nova OS atribuída a você')->body('OS 123 — <b>Guindaste</b>')->sendToDatabase($user);

        $this->assertCount(1, $fake->enviados);
        $this->assertSame('Nova OS atribuída a você', $fake->enviados[0]['title']);
        $this->assertSame('OS 123 — Guindaste', $fake->enviados[0]['body']);
    }

    public function test_categoria_desligada_nao_gera_push_e_sem_aparelho_nao_envia(): void
    {
        $mudo = $this->usuario(['muted_notification_categories' => ['pendencias']]);
        $this->aparelho($mudo);
        $semAparelho = $this->usuario();
        $fake = $this->sender();

        Notification::make()->title('Nova pendência: OS 9')->sendToDatabase($mudo);
        Notification::make()->title('Qualquer aviso')->sendToDatabase($semAparelho);

        $this->assertCount(0, $fake->enviados);
        $this->assertSame(0, $mudo->notifications()->count());
    }

    public function test_aparelho_expirado_e_apagado(): void
    {
        $user = $this->usuario();
        $this->aparelho($user);
        $this->sender(false);

        Notification::make()->title('Aviso')->sendToDatabase($user);

        $this->assertSame(0, PushSubscription::withoutGlobalScopes()->where('user_id', $user->id)->count());
    }

    public function test_lista_marca_como_lida_limpa_e_esconde_avisos_da_central(): void
    {
        $user = $this->usuario();
        Notification::make()->title('Aviso do app')->sendToDatabase($user);
        Notification::make()->title('Mensalidade paga')->viewData(['scope' => 'central'])->sendToDatabase($user);

        $this->celular()->actingAs($user)->get('/colaborador')->assertOk()->assertSee('>1<', false);

        $this->celular()->actingAs($user)->get('/colaborador/notificacoes')->assertOk()->assertSee('Aviso do app')->assertDontSee('Mensalidade paga');
        $this->assertSame(1, $user->notifications()->whereNotNull('read_at')->count());

        $this->celular()->actingAs($user)->post('/colaborador/notificacoes/limpar')->assertRedirect(route('colab.notificacoes'));
        $this->assertSame(1, $user->notifications()->count()); // só sobra a da Central, que o app nunca mostra nem apaga
    }

    public function test_service_worker_so_para_celular_e_com_tipo_certo(): void
    {
        $this->celular()->get('/colaborador/sw.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8')->assertSee('showNotification', false);
    }
}
