<?php

namespace Tests\Feature;

use App\Console\Commands\CheckMaintenanceDueAlerts;
use App\Models\Asset;
use App\Models\MaintenanceDueAlert;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Producao (22 e 23/11/2026): um destinatario com caixa inexistente (SMTP 550)
 * derrubava maintenance:check-due-alerts inteiro, todo dia as 00:00.
 */
class CheckMaintenanceDueAlertsMailFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_um_destinatario_com_envio_recusado_nao_derruba_a_rodada(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Mail '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_plans', 'tabela_assets'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Mail '.uniqid(), 'slug' => 'tenant-mail-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $role = new Role(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $role->save();
        foreach (['inexistente@exemplo.test', 'valido@exemplo.test'] as $email) {
            $u = User::create(['name' => $email, 'email' => $email, 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
            $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
            $u->assignRole($role);
        }
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Mail', 'status' => Asset::STATUS_LOCADO]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Critico vencido',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60), 'is_critical' => true,
        ]);

        $dispatcher = new class implements Dispatcher
        {
            public array $entregues = [];

            public function send($notifiables, $notification): void
            {
                foreach (Arr::wrap($notifiables) as $n) {
                    if ($n->email === 'inexistente@exemplo.test') {
                        throw new RuntimeException('550 5.4.6 Mailbox does not exist');
                    }
                    $this->entregues[] = $n->email;
                }
            }

            public function sendNow($notifiables, $notification, ?array $channels = null): void
            {
                $this->send($notifiables, $notification);
            }
        };
        $this->app->instance(Dispatcher::class, $dispatcher);
        Log::spy();

        $this->artisan(CheckMaintenanceDueAlerts::class)->assertSuccessful();

        $this->assertContains('valido@exemplo.test', $dispatcher->entregues);
        $this->assertSame(1, MaintenanceDueAlert::where('asset_id', $asset->id)->count(), 'dedupe de 7 dias deve ser gravado');
        $this->assertSame(Asset::STATUS_MANUTENCAO, $asset->fresh()->status, 'o bloqueio por item critico deve continuar rodando');
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $c) => ($c['email'] ?? null) === 'inexistente@exemplo.test')->once();
    }
}
