<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\BlockRegistry;
use App\Dashboards\DashboardRenderer;
use App\Dashboards\ModuleDashboards;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\LocadoraDemoService;
use App\Support\SaaSRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Todos os painéis "Visão geral" usam só blocos registrados e renderizam (inclusive vazios) com os formatos modernos. */
class VisaoGeralModernaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_todo_bloco_do_layout_esta_registrado(): void
    {
        $registrados = collect(BlockRegistry::all())->map(fn ($c) => $c::key())->all();
        foreach (ModuleDashboards::all() as $modulo => $def) {
            foreach ($def['blocks'] as $chave) {
                $this->assertContains($chave, $registrados, "$modulo: $chave");
            }
            $this->assertSame(count($def['blocks']), count(array_unique($def['blocks'])), $modulo);
        }
    }

    public function test_paineis_renderizam_sem_erro_e_com_formatos_modernos(): void
    {
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        app(LocadoraDemoService::class)->criar('SenhaDemo123');
        $admin = User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->firstOrFail();
        // libera todos os modulos so' para exercitar o codigo dos blocos
        $plano = Plan::findOrFail(Tenant::withoutGlobalScopes()->findOrFail($admin->tenant_id)->plan_id);
        $plano->features = collect(SaaSRegistry::modules())->pluck('feature')->mapWithKeys(fn ($k) => [$k => true])->all();
        $plano->save();
        $this->actingAs($admin);

        $tipos = [];
        foreach (array_keys(ModuleDashboards::all()) as $modulo) {
            foreach (DashboardRenderer::blocks($modulo, $admin) as $bloco) {
                $this->assertIsArray($bloco['payload'], $bloco['key']);
                $tipos[$bloco['payload']['type']] = true;
            }
        }
        foreach (['donut', 'timeline', 'ranking', 'heat', 'kpi', 'cols'] as $tipo) {
            $this->assertArrayHasKey($tipo, $tipos, $tipo);
        }
    }
}
