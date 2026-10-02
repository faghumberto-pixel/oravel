<?php

namespace Tests\Feature;

use App\Filament\Central\Pages\AcessosClientes;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\AccessAnalytics;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AcessosClientesTest extends TestCase
{
    use DatabaseTransactions;

    private function ev(string $at, string $action, ?string $label = null): array
    {
        return ['at' => Carbon::parse($at), 'action' => $action, 'label' => $label, 'path' => null];
    }

    public function test_sessoes_tempo_por_tela_e_corte_de_ociosidade(): void
    {
        $sessions = AccessAnalytics::sessions([
            $this->ev('2026-10-01 09:00:00', 'login'),
            $this->ev('2026-10-01 09:01:00', 'view', 'Ativos'),      // 4 min até a próxima
            $this->ev('2026-10-01 09:05:00', 'view', 'Clientes'),    // 20 min -> corta em 10
            $this->ev('2026-10-01 09:25:00', 'view', 'Ativos'),      // último da sessão
            $this->ev('2026-10-01 14:00:00', 'login'),               // nova sessão
            $this->ev('2026-10-01 14:02:00', 'view', 'Contratos'),
        ]);

        $this->assertCount(2, $sessions);
        $this->assertSame('2026-10-01 14:00:00', $sessions[0]['start']->format('Y-m-d H:i:s')); // mais recente primeiro
        $first = $sessions[1];
        $this->assertSame((1 + 4 + 10) * 60, $first['seconds']);

        $sum = AccessAnalytics::summary($sessions);
        $this->assertSame(2, $sum['logins']);
        $this->assertSame(2, $sum['sessions']);
        $this->assertSame('Clientes', $sum['top_screen']);
        $this->assertSame(600, $sum['by_screen']['Clientes']);
        $this->assertSame('< 1 min', AccessAnalytics::duration(30));
        $this->assertSame('1h 05min', AccessAnalytics::duration(3900));
    }

    public function test_pagina_central_lista_usuario_online_e_so_do_cliente_escolhido(): void
    {
        $plan = Plan::create(['name' => 'P', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $a = Tenant::create(['name' => 'Cliente A', 'slug' => 'a-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $b = Tenant::create(['name' => 'Cliente B', 'slug' => 'b-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $mk = function (Tenant $t, string $name, bool $online) {
            $u = User::create(['name' => $name, 'email' => str($name)->slug().'-'.uniqid().'@t.com', 'password' => 'x', 'tenant_id' => $t->id]);
            $u->forceFill(['last_seen' => $online ? now() : now()->subHours(3)])->save();
            foreach ([['login', null], ['view', 'Ativos']] as $i => [$action, $label]) {
                UserActivityLog::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'user_id' => $u->id, 'method' => 'GET', 'path' => '/admin/assets', 'action' => $action, 'resource_label' => $label])
                    ->forceFill(['created_at' => now()->subMinutes(10 - $i)])->save();
            }

            return $u;
        };
        $on = $mk($a, 'Ana Online', true);
        $mk($a, 'Beto Offline', false);
        $mk($b, 'Carla Outra', true);

        $rows = Livewire::test(AcessosClientes::class)->set('tenantId', $a->id)->assertSee('1 usuário online agora')->assertSee('Ana Online')->assertDontSee('Carla Outra')->instance()->rows();

        $this->assertSame(['Ana Online', 'Beto Offline'], $rows->pluck('name')->all()); // online primeiro; só o cliente A
        $this->assertTrue($rows[0]['online']);
        $this->assertFalse($rows[1]['online']);
        $this->assertSame($on->id, $rows[0]['user_id']);
    }
}
