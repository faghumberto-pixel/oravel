<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\Blocks\Ativos\AtivosPorCategoria;
use App\Dashboards\Blocks\Ativos\AtivosPorCliente;
use App\Dashboards\Blocks\Ativos\TaxaDeLocacao;
use App\Dashboards\Blocks\Ativos\TotalDeAtivos;
use App\Dashboards\Blocks\Ativos\VencimentosDeDocumentos;
use App\Dashboards\DashboardRenderer;
use App\Filament\Pages\VisaoGeralAtivos;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\HubMenus;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardsAtivosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(array $features = ['tabela_assets' => true]): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'at-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function ativo(Tenant $t, array $attrs = []): Asset
    {
        return Asset::unguarded(fn () => Asset::create($attrs + ['tenant_id' => $t->id, 'name' => 'Ativo '.uniqid(), 'status' => 'disponivel']));
    }

    public function test_sem_o_modulo_ativos_a_pagina_nao_existe(): void
    {
        [, $admin] = $this->empresa(['tabela_clients' => true]);
        $this->actingAs($admin);

        $this->assertFalse(VisaoGeralAtivos::canAccess());
        $this->assertSame([], DashboardRenderer::blocks('ativos', $admin));
    }

    public function test_total_e_valor_de_aquisicao_so_do_proprio_tenant(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $this->ativo($a, ['acquisition_value' => 1000]);
        $this->ativo($a, ['acquisition_value' => 500]);
        $this->ativo($b, ['acquisition_value' => 99999]);
        $this->actingAs($adminA);

        $p = (new TotalDeAtivos)->data([]);

        $this->assertSame(2, $p['value']);
        $this->assertStringContainsString('1.500,00', $p['delta']);
    }

    public function test_taxa_de_locacao(): void
    {
        [$t, $admin] = $this->empresa();
        foreach (['locado', 'locado', 'disponivel', 'manutencao'] as $status) {
            $this->ativo($t, ['status' => $status]);
        }
        $this->actingAs($admin);

        $p = (new TaxaDeLocacao)->data([]);

        $this->assertSame('50%', $p['value']);
        $this->assertSame(50, $p['bar']['pct']);
    }

    public function test_taxa_de_locacao_sem_ativos_nao_divide_por_zero(): void
    {
        [, $admin] = $this->empresa();
        $this->actingAs($admin);

        $this->assertSame('—', (new TaxaDeLocacao)->data([])['value']);
    }

    public function test_por_categoria_agrupa_e_nomeia_sem_categoria(): void
    {
        [$t, $admin] = $this->empresa();
        $cat = AssetCategory::unguarded(fn () => AssetCategory::create(['tenant_id' => $t->id, 'name' => 'Gerador']));
        $this->ativo($t, ['asset_category_id' => $cat->id]);
        $this->ativo($t, ['asset_category_id' => $cat->id]);
        $this->ativo($t);
        $this->actingAs($admin);

        $linhas = (new AtivosPorCategoria)->data([])['rows'];

        $this->assertSame(['Gerador', 2, '2'], $linhas[0]);
        $this->assertSame('Sem categoria', $linhas[1][0]);
    }

    public function test_locados_por_cliente_conta_so_os_locados(): void
    {
        [$t, $admin] = $this->empresa();
        $c = Client::unguarded(fn () => Client::create(['tenant_id' => $t->id, 'name' => 'Construtora']));
        $this->ativo($t, ['status' => 'locado', 'client_id' => $c->id]);
        $this->ativo($t, ['status' => 'locado', 'client_id' => $c->id]);
        $this->ativo($t, ['status' => 'disponivel', 'client_id' => $c->id]);
        $this->actingAs($admin);

        $linhas = (new AtivosPorCliente)->data([])['rows'];

        $this->assertSame(['Construtora', 2, '2'], $linhas[0]);
    }

    public function test_documentos_a_vencer_marca_vencidos_e_ignora_os_distantes(): void
    {
        [$t, $admin] = $this->empresa();
        $this->ativo($t, ['name' => 'Munck', 'placa' => 'AAA1A11', 'seguro_vencimento' => now()->subDays(3)->toDateString()]);
        $this->ativo($t, ['name' => 'Toco', 'placa' => 'BBB2B22', 'ipva_vencimento' => now()->addDays(10)->toDateString()]);
        $this->ativo($t, ['name' => 'Longe', 'licenciamento_vencimento' => now()->addDays(200)->toDateString()]);
        $this->actingAs($admin);

        $linhas = (new VencimentosDeDocumentos)->data(['dias' => 30])['rows'];

        $this->assertCount(2, $linhas);
        $this->assertSame(['Munck', 'AAA1A11', 'Seguro'], array_slice($linhas[0], 0, 3));
        $this->assertSame('crit', $linhas[0][4]['pill']);
        $this->assertSame('warn', $linhas[1][4]['pill']);
    }

    public function test_abrir_o_grupo_ativos_leva_a_visao_geral(): void
    {
        [, $admin] = $this->empresa();
        $this->actingAs($admin);
        app()->forgetInstance(NavigationManager::class);

        $this->assertSame(VisaoGeralAtivos::getUrl(), HubMenus::paginaPrincipalDeCadaGrupo()['Ativos'] ?? null);
    }

    public function test_a_pagina_renderiza(): void
    {
        [$t, $admin] = $this->empresa();
        $this->ativo($t, ['name' => 'Gerador Alfa', 'seguro_vencimento' => now()->addDays(5)->toDateString()]);
        $this->actingAs($admin);

        Livewire::test(VisaoGeralAtivos::class)
            ->assertSee('Ativos cadastrados')
            ->assertSee('Taxa de locação')
            ->assertSee('Documentos a vencer')
            ->assertSee('Gerador Alfa');
    }
}
