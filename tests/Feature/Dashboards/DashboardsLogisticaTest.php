<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\Blocks\Logistica\FreteNoMes;
use App\Dashboards\Blocks\Logistica\MovimentacoesDeHoje;
use App\Dashboards\Blocks\Logistica\MovimentacoesEmCurso;
use App\Dashboards\Blocks\Logistica\RetiradasSolicitadas;
use App\Dashboards\Blocks\Logistica\VeiculosForaDoPatio;
use App\Dashboards\DashboardRenderer;
use App\Filament\Pages\VisaoGeralLogistica;
use App\Models\Asset;
use App\Models\Client;
use App\Models\EquipmentMovement;
use App\Models\EquipmentPickupRequest;
use App\Models\FreightRecord;
use App\Models\FrotaSaidaVeiculo;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\HubMenus;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardsLogisticaTest extends TestCase
{
    use DatabaseTransactions;

    private const FEATURES = [
        'tabela_equipment_movements' => true, 'tabela_freight_records' => true, 'tabela_equipment_pickup_requests' => true, 'tabela_frota_saidas_veiculo' => true,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(array $features = self::FEATURES): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'lg-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function criar(string $modelo, array $attrs): Model
    {
        return $modelo::unguarded(fn () => $modelo::create($attrs));
    }

    private function ativo(Tenant $t, string $nome = 'Empilhadeira'): Asset
    {
        return $this->criar(Asset::class, ['tenant_id' => $t->id, 'name' => $nome, 'status' => 'disponivel']);
    }

    private function mov(Tenant $t, array $attrs = []): EquipmentMovement
    {
        return $this->criar(EquipmentMovement::class, $attrs + ['tenant_id' => $t->id, 'asset_id' => $this->ativo($t)->id, 'type' => 'mobilizacao', 'status' => 'aguardando_vistoria', 'scheduled_at' => now()]);
    }

    public function test_sem_logistica_no_plano_a_pagina_nao_existe(): void
    {
        [, $admin] = $this->empresa(['tabela_assets' => true]);
        $this->actingAs($admin);

        $this->assertFalse(VisaoGeralLogistica::canAccess());
    }

    public function test_so_blocos_dos_modulos_contratados(): void
    {
        [, $admin] = $this->empresa(['tabela_equipment_movements' => true]);
        $this->actingAs($admin);

        $chaves = array_column(DashboardRenderer::blocks('logistica', $admin), 'key');

        $this->assertContains('logistica.movimentacoes_hoje', $chaves);
        $this->assertNotContains('logistica.frete_mes', $chaves);
        $this->assertNotContains('logistica.veiculos_fora', $chaves);
    }

    public function test_movimentacoes_de_hoje_separa_por_tipo_e_isola_tenant(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $this->mov($a, ['type' => 'mobilizacao']);
        $this->mov($a, ['type' => 'desmobilizacao']);
        $this->mov($a, ['scheduled_at' => now()->subDays(3)]);
        $this->mov($b);
        $this->actingAs($adminA);

        $p = (new MovimentacoesDeHoje)->data([]);

        $this->assertSame(2, $p['value']);
        $this->assertStringContainsString('1 mobilização', $p['delta']);
    }

    public function test_em_curso_ignora_concluidas_e_avisa_as_aguardando_aprovacao(): void
    {
        [$t, $admin] = $this->empresa();
        $this->mov($t, ['status' => 'em_andamento']);
        $this->mov($t, ['status' => 'aguardando_aprovacao']);
        $this->mov($t, ['status' => 'concluido']);
        $this->actingAs($admin);

        $p = (new MovimentacoesEmCurso)->data([]);

        $this->assertSame(2, $p['value']);
        $this->assertStringContainsString('1 aguardando aprovação', $p['delta']);
        $this->assertSame('dn', $p['tone']);
    }

    public function test_frete_do_mes_compara_com_o_anterior(): void
    {
        [$t, $admin] = $this->empresa();
        $mov = $this->mov($t);
        $base = ['tenant_id' => $t->id, 'equipment_movement_id' => $mov->id, 'tipo' => 'proprio'];
        $this->criar(FreightRecord::class, $base + ['valor' => 600, 'data' => now()->toDateString()]);
        $this->criar(FreightRecord::class, $base + ['valor' => 400, 'data' => now()->subMonth()->toDateString()]);
        $this->actingAs($admin);

        $p = (new FreteNoMes)->data([]);

        $this->assertStringContainsString('600,00', $p['value']);
        $this->assertStringContainsString('+50%', $p['delta']);
        $this->assertSame('dn', $p['tone']); // frete subiu: ruim
    }

    public function test_retiradas_solicitadas_conta_so_as_sem_agenda(): void
    {
        [$t, $admin] = $this->empresa();
        $cliente = $this->criar(Client::class, ['tenant_id' => $t->id, 'name' => 'Cliente']);
        $ativo = $this->ativo($t);
        $base = ['tenant_id' => $t->id, 'client_id' => $cliente->id, 'asset_id' => $ativo->id];
        $this->criar(EquipmentPickupRequest::class, $base + ['status' => 'solicitado', 'requested_at' => now()->subDays(5)]);
        $this->criar(EquipmentPickupRequest::class, $base + ['status' => 'solicitado', 'requested_at' => now()]);
        $this->criar(EquipmentPickupRequest::class, $base + ['status' => 'agendado', 'requested_at' => now()->subDays(9)]);
        $this->actingAs($admin);

        $p = (new RetiradasSolicitadas)->data([]);

        $this->assertSame(2, $p['value']);
        $this->assertStringContainsString('1 há mais de 3 dias', $p['delta']);
    }

    public function test_veiculos_fora_marca_os_que_passaram_do_limite_de_horas(): void
    {
        [$t, $admin] = $this->empresa();
        $v = $this->ativo($t, 'Munck');
        $base = ['tenant_id' => $t->id, 'ativo_id' => $v->id, 'finalidade' => 'locacao', 'destino' => 'Obra X', 'motivo' => 'Entrega', 'odometro_saida' => 100];
        $this->criar(FrotaSaidaVeiculo::class, $base + ['saida_em' => now()->subHours(30)]);
        $this->criar(FrotaSaidaVeiculo::class, $base + ['saida_em' => now()->subHours(30), 'retorno_em' => now()]);
        $this->actingAs($admin);

        $linhas = (new VeiculosForaDoPatio)->data([])['rows'];

        $this->assertCount(1, $linhas);
        $this->assertSame('crit', $linhas[0][3]['pill']);
    }

    public function test_abrir_o_grupo_logistica_leva_a_visao_geral(): void
    {
        [, $admin] = $this->empresa();
        $this->actingAs($admin);
        app()->forgetInstance(NavigationManager::class);

        $this->assertSame(VisaoGeralLogistica::getUrl(), HubMenus::paginaPrincipalDeCadaGrupo()['Logística'] ?? null);
    }

    public function test_a_pagina_renderiza(): void
    {
        [$t, $admin] = $this->empresa();
        $this->mov($t);
        $this->actingAs($admin);

        Livewire::test(VisaoGeralLogistica::class)
            ->assertSee('Movimentações hoje')
            ->assertSee('Programação')
            ->assertSee('Mobilização x desmobilização');
    }
}
