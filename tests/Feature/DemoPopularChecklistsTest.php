<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\CatalogoItem;
use App\Models\CkExecution;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Checklists\ChecklistInstalador;
use App\Services\Checklists\ChecklistVisaoGeral;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoPopularChecklistsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_popula_cobre_todos_os_diagnosticos_e_remove_so_o_que_criou(): void
    {
        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('banco nao descartavel');
        }

        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [CatalogoItem::FEATURE_KEY => true, CkTemplate::FEATURE_KEY => true]]);
        $t = Tenant::create(['name' => 'Locadora Demo Teste', 'slug' => 'ldt-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        User::create(['name' => 'Ana', 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        app(CatalogoInstalador::class)->instalar($t);
        app(ChecklistInstalador::class)->instalar($t);
        $propria = Asset::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'name' => 'Ativo real do cliente', 'tag' => 'REAL-1', 'status' => Asset::STATUS_DISPONIVEL]);

        $this->artisan('demo:popular-checklists', ['--tenant' => $t->name])->expectsOutputToContain('Criados: 22 ativos, 10 ordens de serviço, 12 checklists preenchidos.')->assertSuccessful();
        $this->artisan('demo:popular-checklists', ['--tenant' => $t->name])->assertFailed(); // idempotente: nao duplica

        $this->actingAs(User::withoutGlobalScopes()->where('tenant_id', $t->id)->first());
        $svc = app(ChecklistVisaoGeral::class);
        $c = $svc->resumoCorretivas([]);
        foreach (['sem_checklist', 'nunca_conferido', 'ja_registrado', 'conferido', 'sem_item'] as $d) {
            $this->assertSame(1, $c[$d], "diagnostico $d");
        }
        $cob = $svc->resumoCobertura([]);
        $this->assertGreaterThan(0, $cob['em_dia']);
        $this->assertGreaterThan(0, $cob['atrasado']);
        $this->assertGreaterThan(0, $cob['nunca']);
        $this->assertGreaterThan(0, $cob['sem_checklist']);
        $s = $svc->resumoServicos([]);
        $this->assertGreaterThan(0, $s['feito']);
        $this->assertGreaterThan(0, $s['pendente']);

        $this->artisan('demo:popular-checklists', ['--tenant' => $t->name, '--remover' => true])->assertSuccessful();
        $this->assertSame(0, Asset::withoutGlobalScopes()->where('tenant_id', $t->id)->where('tag', 'like', 'CKD-%')->count());
        $this->assertSame(0, CkExecution::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
        $this->assertSame(0, MaintenanceOrder::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
        $this->assertNotNull(Asset::withoutGlobalScopes()->find($propria->id), 'o ativo do cliente permanece');
    }
}
