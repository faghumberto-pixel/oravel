<?php

namespace Tests\Feature;

use App\Models\AbcMatrix;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** API das tarefas do dia do técnico (app de campo offline): a criticidade vem da Matriz ABC do ativo. */
class TechnicianTasksOfDayApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tarefas_do_dia_trazem_a_criticidade_da_matriz_abc_sem_erro(): void
    {
        $plan = Plan::create(['name' => 'Plano TT '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets' => true]]);
        $t = Tenant::create(['name' => 'Locadora TT', 'slug' => 'tt-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $tec = User::create(['name' => 'Téc', 'email' => 'tt-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);

        $critico = Asset::create(['tenant_id' => $t->id, 'name' => 'Guindaste A', 'patrimonio' => 'PAT-A1', 'status' => 'manutencao']);
        $semMatriz = Asset::create(['tenant_id' => $t->id, 'name' => 'Gerador B', 'patrimonio' => 'PAT-B1', 'status' => 'manutencao']);
        AbcMatrix::create(['tenant_id' => $t->id, 'asset_id' => $critico->id, 'nivel' => 'A', 'descricao' => 'Crítico para a operação']);

        foreach ([$critico, $semMatriz] as $i => $ativo) {
            MaintenanceOrder::withoutEvents(fn () => MaintenanceOrder::create([
                'tenant_id' => $t->id, 'asset_id' => $ativo->id, 'os_number' => 'OS-TT-'.$i.random_int(100, 999),
                'status' => 'Aberto', 'internal_status' => 'em_manutencao', 'technician_id' => $tec->id, 'description' => 'Revisão',
            ]));
        }

        Sanctum::actingAs($tec);
        $json = $this->getJson('/api/technician/tasks-of-day')->assertOk()->json();

        $porAtivo = collect($json['tasks'])->keyBy('asset_id');
        $this->assertSame('A', $porAtivo[$critico->id]['criticality']);
        $this->assertSame('C', $porAtivo[$semMatriz->id]['criticality'], 'sem Matriz ABC cai no padrão C');
        $this->assertSame('A', collect($json['assets'])->firstWhere('id', $critico->id)['criticality']);
    }
}
