<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\BlockRegistry;
use App\Dashboards\DashboardRenderer;
use App\Dashboards\ModuleDashboards;
use App\Filament\Pages\PainelControleAdministrativo;
use App\Filament\Pages\PainelControleCompras;
use App\Filament\Pages\PainelControlePlanejamento;
use App\Filament\Pages\PainelControleRh;
use App\Filament\Pages\VisaoGeralFinanceiro;
use App\Models\Asset;
use App\Models\DocumentSignature;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TimeClock;
use App\Models\User;
use App\Support\AppProfile;
use App\Support\PainelDaArea;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Painel de Controle por area (Administrativo, RH, Compras, Planejamento) e a tela inicial de cada perfil. */
class PaineisPorAreaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** @return array{0: Tenant, 1: User} */
    private function empresa(array $features): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'a-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    /** Usuario comum (nao administrador) com um perfil que le so os recursos dados. */
    private function colega(Tenant $t, array $leituras): User
    {
        $u = User::create(['name' => 'Colega', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $perfil = Role::create(['name' => 'perfil-'.uniqid(), 'guard_name' => 'web', 'tenant_id' => $t->id]);

        foreach ($leituras as $slug) {
            $perfil->givePermissionTo(Permission::firstOrCreate(['name' => 'ler_'.$slug, 'guard_name' => 'web']));
        }

        $u->assignRole($perfil);

        return $u->refresh();
    }

    private function criar(string $modelo, array $attrs)
    {
        return $modelo::unguarded(fn () => $modelo::create($attrs));
    }

    public function test_os_quatro_paineis_novos_existem_com_o_nome_de_painel_de_controle_e_so_blocos_registrados(): void
    {
        foreach (['administrativo' => 'Administrativo', 'rh' => 'Recursos Humanos', 'compras' => 'Compras', 'planejamento' => 'Planejamento'] as $chave => $nome) {
            $d = ModuleDashboards::find($chave);
            $this->assertSame('Painel de Controle — '.$nome, $d['label']);
            $this->assertNotEmpty($d['blocks']);

            foreach ($d['blocks'] as $b) {
                $this->assertNotNull(BlockRegistry::find($b), "$chave usa bloco nao registrado: $b");
            }
        }
    }

    public function test_rh_mostra_colaboradores_ponto_e_certificacoes_a_vencer(): void
    {
        [$t, $admin] = $this->empresa(['tabela_employees' => true, 'tabela_time_clocks' => true]);
        $ana = $this->criar(Employee::class, ['tenant_id' => $t->id, 'name' => 'Ana Souza', 'cpf' => '11144477735', 'status' => Employee::STATUS_ATIVO, 'admission_date' => now()->subMonths(2)->toDateString()]);
        $this->criar(Employee::class, ['tenant_id' => $t->id, 'name' => 'Beto Lima', 'cpf' => '52998224725', 'status' => Employee::STATUS_AFASTADO, 'admission_date' => now()->subYear()->toDateString()]);
        $this->criar(EmployeeCertification::class, ['tenant_id' => $t->id, 'employee_id' => $ana->id, 'norma' => 'NR-35', 'data_validade' => now()->addDays(10)->toDateString()]);
        $this->criar(EmployeeCertification::class, ['tenant_id' => $t->id, 'employee_id' => $ana->id, 'norma' => 'NR-10', 'data_validade' => now()->subDays(3)->toDateString()]);
        $this->criar(EmployeeCertification::class, ['tenant_id' => $t->id, 'employee_id' => $ana->id, 'norma' => 'NR-12', 'data_validade' => now()->addYear()->toDateString()]);
        $this->criar(TimeClock::class, ['tenant_id' => $t->id, 'employee_id' => $ana->id, 'client_uuid' => (string) Str::uuid(), 'tipo' => TimeClock::TIPO_ENTRADA, 'recorded_at' => now(), 'device_recorded_at' => now(), 'sync_status' => 'synced']);
        $this->actingAs($admin);

        $blocos = collect(DashboardRenderer::blocks('rh', $admin))->keyBy('key');

        $this->assertSame(1, $blocos['rh.colaboradores_ativos']['payload']['value']);
        $this->assertStringContainsString('1 afastado', $blocos['rh.colaboradores_ativos']['payload']['delta']);
        $this->assertSame(1, $blocos['rh.ponto_hoje']['payload']['value']);
        $linhas = $blocos['rh.certificacoes_a_vencer']['payload']['rows'];
        $this->assertCount(2, $linhas, 'so a vencida e a que vence em 60 dias; a de 1 ano fica de fora');
        $this->assertSame('NR-10', $linhas[0][1], 'a vencida vem primeiro');
        $this->assertSame('crit', $linhas[0][3]['pill']);
        $this->assertArrayNotHasKey('rh.epi_a_devolver', $blocos->all(), 'sem o modulo de EPI contratado, o bloco nao aparece');
    }

    public function test_administrativo_conta_assinaturas_pendentes_e_vencidas(): void
    {
        [$t, $admin] = $this->empresa(['assinatura_eletronica' => true]);
        $base = ['tenant_id' => $t->id, 'signable_type' => 'App\\Models\\Contract', 'signable_id' => (string) Str::uuid(), 'signer_name' => 'Cliente'];
        $this->criar(DocumentSignature::class, $base + ['token' => Str::random(40), 'status' => 'pending', 'expires_at' => now()->addDays(5)]);
        $this->criar(DocumentSignature::class, $base + ['token' => Str::random(40), 'status' => 'pending', 'expires_at' => now()->subDay()]);
        $this->criar(DocumentSignature::class, $base + ['token' => Str::random(40), 'status' => 'signed', 'expires_at' => now()->addDays(5)]);
        $this->actingAs($admin);

        $blocos = collect(DashboardRenderer::blocks('administrativo', $admin))->keyBy('key');

        $this->assertSame(2, $blocos['administrativo.assinaturas_pendentes']['payload']['value']);
        $this->assertStringContainsString('1 com o prazo vencido', $blocos['administrativo.assinaturas_pendentes']['payload']['delta']);
        $this->assertSame('3', $blocos['administrativo.assinaturas_situacao']['payload']['center']);
    }

    public function test_cada_painel_novo_abre_so_com_modulo_contratado(): void
    {
        [, $comRh] = $this->empresa(['tabela_employees' => true]);
        $this->actingAs($comRh);
        $this->assertTrue(PainelControleRh::canAccess());
        $this->assertFalse(PainelControleCompras::canAccess());
        $this->assertFalse(PainelControleAdministrativo::canAccess());
        $this->assertFalse(PainelControlePlanejamento::canAccess());
        Livewire::test(PainelControleRh::class)->assertSee('Painel de Controle — Recursos Humanos')->assertSee('Colaboradores ativos');
        Livewire::test(PainelControleCompras::class)->assertForbidden();
    }

    public function test_quem_nao_e_administrador_abre_no_painel_da_sua_area_e_o_dono_continua_no_painel_geral(): void
    {
        [$t, $dono] = $this->empresa(['tabela_account_receivables' => true, 'tabela_account_payables' => true, 'tabela_employees' => true]);

        $financeiro = $this->colega($t, ['contas_receber', 'contas_pagar']);
        $rh = $this->colega($t, ['colaborador']);
        $semNada = $this->colega($t, []);

        $this->actingAs($financeiro);
        $this->assertSame('financeiro', PainelDaArea::chaveDoPainel($financeiro));
        $this->assertSame(VisaoGeralFinanceiro::getUrl(), AppProfile::homeUrl($financeiro, AppProfile::ADMIN));

        $this->actingAs($rh);
        $this->assertSame('rh', PainelDaArea::chaveDoPainel($rh));
        $this->assertSame(PainelControleRh::getUrl(), AppProfile::homeUrl($rh, AppProfile::ADMIN));

        $this->actingAs($semNada);
        $this->assertNull(PainelDaArea::chaveDoPainel($semNada), 'sem leitura em nenhum painel, a tela inicial segue como sempre');

        $this->actingAs($dono);
        $this->assertNull(PainelDaArea::chaveDoPainel($dono), 'o administrador/dono continua no Painel de Controle geral');
        $this->assertNotSame(VisaoGeralFinanceiro::getUrl(), AppProfile::homeUrl($dono, AppProfile::ADMIN));
    }

    public function test_com_a_flag_dos_paineis_desligada_ninguem_e_redirecionado(): void
    {
        config(['oravel.dashboards_modulo' => false]);
        [$t] = $this->empresa(['tabela_employees' => true]);
        $rh = $this->colega($t, ['colaborador']);
        $this->actingAs($rh);

        $this->assertNull(PainelDaArea::chaveDoPainel($rh));
    }

    public function test_planejamento_separa_preventiva_vencida_da_a_vencer_e_mede_a_cobertura(): void
    {
        [$t, $admin] = $this->empresa(['tabela_maintenance_plans' => true, 'tabela_assets' => true]);
        $vencido = Asset::factory()->create(['tenant_id' => $t->id, 'name' => 'Gerador vencido', 'horimetro_atual' => 600, 'asset_category_id' => null, 'checklist_group_id' => null]);
        $quase = Asset::factory()->create(['tenant_id' => $t->id, 'name' => 'Gerador quase', 'horimetro_atual' => 480, 'asset_category_id' => null, 'checklist_group_id' => null]);
        Asset::factory()->create(['tenant_id' => $t->id, 'name' => 'Gerador sem plano', 'horimetro_atual' => 10, 'asset_category_id' => null, 'checklist_group_id' => null]);

        foreach ([$vencido, $quase] as $a) {
            $this->criar(MaintenancePlan::class, ['tenant_id' => $t->id, 'asset_id' => $a->id, 'name' => 'Troca de óleo', 'interval_hours' => 500, 'last_service_hours' => 0, 'is_active' => true]);
        }

        $this->actingAs($admin);
        $blocos = collect(DashboardRenderer::blocks('planejamento', $admin))->keyBy('key');

        $this->assertSame(1, $blocos['pmp.preventivas_vencidas']['payload']['value']);
        $this->assertStringContainsString('1 vencem', $blocos['pmp.preventivas_vencidas']['payload']['delta']);
        $this->assertSame('Gerador vencido', $blocos['pmp.mais_atrasadas']['payload']['rows'][0][0]);
        $this->assertSame('Gerador quase', $blocos['pmp.a_vencer']['payload']['rows'][0][0]);
        $this->assertSame(67, $blocos['pmp.cobertura']['payload']['value'], '2 de 3 equipamentos com plano');
    }

    public function test_compras_destaca_pedido_atrasado_e_soma_por_fornecedor(): void
    {
        [$t, $admin] = $this->empresa(['tabela_purchase_orders' => true]);
        $fornecedor = $this->criar(Supplier::class, ['tenant_id' => $t->id, 'name' => 'Peças Ltda']);
        $this->criar(PurchaseOrder::class, ['tenant_id' => $t->id, 'supplier_id' => $fornecedor->id, 'created_by_user_id' => $admin->id, 'status' => PurchaseOrder::STATUS_ABERTA, 'total_value' => 1500, 'expected_delivery_date' => now()->subDays(4)->toDateString()]);
        $this->criar(PurchaseOrder::class, ['tenant_id' => $t->id, 'supplier_id' => $fornecedor->id, 'created_by_user_id' => $admin->id, 'status' => PurchaseOrder::STATUS_ABERTA, 'total_value' => 500, 'expected_delivery_date' => now()->addDays(4)->toDateString()]);
        $this->criar(PurchaseOrder::class, ['tenant_id' => $t->id, 'supplier_id' => $fornecedor->id, 'created_by_user_id' => $admin->id, 'status' => PurchaseOrder::STATUS_CANCELADA, 'total_value' => 9999, 'expected_delivery_date' => now()->subDays(9)->toDateString()]);
        $this->actingAs($admin);

        $blocos = collect(DashboardRenderer::blocks('compras', $admin))->keyBy('key');

        $atrasados = $blocos['compras.pedidos_atrasados']['payload']['rows'];
        $this->assertCount(1, $atrasados, 'cancelado e no prazo ficam de fora');
        $this->assertSame('Peças Ltda', $atrasados[0][0]);
        $this->assertStringContainsString('4 d de atraso', $atrasados[0][2]['text']);
        $this->assertStringContainsString('2.000,00', $blocos['compras.por_fornecedor']['payload']['rows'][0][3], 'soma dos nao cancelados');
    }
}
