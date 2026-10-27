<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\Blocks\Comercial\ClientesParaLigar;
use App\Dashboards\Blocks\Comercial\ContratosAtivos;
use App\Dashboards\Blocks\Comercial\ContratosVencendo;
use App\Dashboards\Blocks\Comercial\ConversaoDeOrcamentos;
use App\Dashboards\Blocks\Comercial\FunilDeVendas;
use App\Dashboards\Blocks\Comercial\OrcamentosSemRetorno;
use App\Dashboards\DashboardRenderer;
use App\Filament\Pages\VisaoGeralComercial;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CrmLead;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardsComercialTest extends TestCase
{
    use DatabaseTransactions;

    private const FEATURES = ['tabela_contracts' => true, 'tabela_clients' => true, 'tabela_quotes' => true, 'tabela_crm_leads' => true, 'tabela_solicitacao_locacao' => true];

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
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'c-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function criar(string $modelo, array $attrs): Model
    {
        return $modelo::unguarded(fn () => $modelo::create($attrs));
    }

    private function cliente(Tenant $t, string $nome = 'Cliente'): Client
    {
        return $this->criar(Client::class, ['tenant_id' => $t->id, 'name' => $nome]);
    }

    private function contrato(Tenant $t, Client $c, array $attrs = []): Contract
    {
        return $this->criar(Contract::class, $attrs + [
            'tenant_id' => $t->id, 'client_id' => $c->id, 'contract_number' => 'C-'.uniqid(), 'status' => 'Ativo',
            'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonths(6)->toDateString(), 'price' => 1000,
        ]);
    }

    public function test_sem_contratos_no_plano_a_pagina_nao_existe(): void
    {
        [, $admin] = $this->empresa(['tabela_assets' => true]);
        $this->actingAs($admin);

        $this->assertFalse(VisaoGeralComercial::canAccess());
    }

    public function test_so_blocos_dos_modulos_contratados(): void
    {
        [, $admin] = $this->empresa(['tabela_contracts' => true]);
        $this->actingAs($admin);

        $chaves = array_column(DashboardRenderer::blocks('comercial', $admin), 'key');

        $this->assertContains('comercial.contratos_ativos', $chaves);
        $this->assertNotContains('comercial.funil', $chaves);
        $this->assertNotContains('comercial.conversao_orcamentos', $chaves);
    }

    public function test_contratos_ativos_so_do_proprio_tenant_e_so_ativos(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $ca = $this->cliente($a);
        $this->contrato($a, $ca, ['price' => 500]);
        $this->contrato($a, $ca, ['status' => 'Encerrado']);
        $this->contrato($b, $this->cliente($b), ['price' => 9999]);
        $this->actingAs($adminA);

        $p = (new ContratosAtivos)->data([]);

        $this->assertSame(1, $p['value']);
        $this->assertStringContainsString('500,00', $p['delta']);
    }

    public function test_contratos_vencendo_conta_30_e_90_dias(): void
    {
        [$t, $admin] = $this->empresa();
        $c = $this->cliente($t);
        $this->contrato($t, $c, ['end_date' => now()->addDays(10)->toDateString()]);
        $this->contrato($t, $c, ['end_date' => now()->addDays(60)->toDateString()]);
        $this->contrato($t, $c, ['end_date' => now()->addDays(200)->toDateString()]);
        $this->actingAs($admin);

        $p = (new ContratosVencendo)->data([]);

        $this->assertSame(1, $p['value']);
        $this->assertStringContainsString('2 em 90 dias', $p['delta']);
    }

    public function test_conversao_de_orcamentos(): void
    {
        [$t, $admin] = $this->empresa();
        $c = $this->cliente($t);
        foreach (['enviado', 'aprovado', 'aprovado', 'reprovado'] as $status) {
            $this->criar(Quote::class, ['tenant_id' => $t->id, 'client_id' => $c->id, 'status' => $status]);
        }
        $this->actingAs($admin);

        $p = (new ConversaoDeOrcamentos)->data([]);

        $this->assertSame('50%', $p['value']);
    }

    public function test_orcamentos_sem_retorno_so_conta_enviados_parados(): void
    {
        [$t, $admin] = $this->empresa();
        $c = $this->cliente($t);
        $this->criar(Quote::class, ['tenant_id' => $t->id, 'client_id' => $c->id, 'status' => 'enviado', 'sent_at' => now()->subDays(8), 'total_value' => 300]);
        $this->criar(Quote::class, ['tenant_id' => $t->id, 'client_id' => $c->id, 'status' => 'enviado', 'sent_at' => now()->subDay(), 'total_value' => 700]);
        $this->criar(Quote::class, ['tenant_id' => $t->id, 'client_id' => $c->id, 'status' => 'enviado', 'sent_at' => now()->subDays(9), 'client_responded_at' => now(), 'total_value' => 50]);
        $this->actingAs($admin);

        $p = (new OrcamentosSemRetorno)->data(['dias' => 5]);

        $this->assertSame(1, $p['value']);
        $this->assertStringContainsString('300,00', $p['delta']);
    }

    public function test_funil_conta_por_etapa_e_isola_tenant(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        foreach (['novo', 'novo', 'qualificado'] as $stage) {
            $this->criar(CrmLead::class, ['tenant_id' => $a->id, 'name' => 'L', 'stage' => $stage]);
        }
        $this->criar(CrmLead::class, ['tenant_id' => $b->id, 'name' => 'Outro', 'stage' => 'novo']);
        $this->actingAs($adminA);

        $passos = (new FunilDeVendas)->data([])['steps'];

        $this->assertSame(['Novos contatos', 2], $passos[0]);
        $this->assertSame(['Qualificados', 1], $passos[2]);
    }

    public function test_clientes_para_ligar_so_quem_nao_tem_contrato_ativo_e_parou_ha_90_dias(): void
    {
        [$t, $admin] = $this->empresa();
        $parado = $this->cliente($t, 'Parado');
        $ativo = $this->cliente($t, 'Ativo');
        $recente = $this->cliente($t, 'Recente');
        $this->contrato($t, $parado, ['status' => 'Encerrado', 'start_date' => now()->subDays(200)->toDateString()]);
        $this->contrato($t, $ativo, ['status' => 'Ativo', 'start_date' => now()->subDays(200)->toDateString()]);
        $this->contrato($t, $recente, ['status' => 'Encerrado', 'start_date' => now()->subDays(20)->toDateString()]);
        $this->actingAs($admin);

        $linhas = (new ClientesParaLigar)->data(['dias' => 90])['rows'];

        $this->assertCount(1, $linhas);
        $this->assertSame('Parado', $linhas[0][0]);
    }

    public function test_a_pagina_renderiza(): void
    {
        [$t, $admin] = $this->empresa();
        $this->contrato($t, $this->cliente($t, 'Construtora X'), ['end_date' => now()->addDays(20)->toDateString()]);
        $this->actingAs($admin);

        Livewire::test(VisaoGeralComercial::class)
            ->assertSee('Contratos ativos')
            ->assertSee('Funil de vendas')
            ->assertSee('Contratos a renovar')
            ->assertSee('Construtora X');
    }
}
