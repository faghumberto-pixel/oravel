<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\LocadoraDemoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Tenant "Locadora Demo" (demo para a ALEC): criar/apagar isolados, idempotentes e com trava de seguranca. */
class LocadoraDemoTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantReal(): Tenant
    {
        $plano = Plan::create(['name' => 'Plano Real '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $tenant = Tenant::create(['name' => 'Cliente Real '.uniqid(), 'slug' => 'real-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Real', 'tag' => 'REAL-'.uniqid(), 'status' => 'disponivel']);
        DB::table('maintenance_orders')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'status' => 'Concluída', 'os_number' => 'REAL-OS-'.uniqid(), 'total_order_cost' => 123.45, 'created_at' => now(), 'updated_at' => now()]);

        return $tenant;
    }

    private function contagensDoTenant(string $tenantId): array
    {
        return collect(['assets', 'maintenance_orders', 'clients', 'contracts', 'users'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->where('tenant_id', $tenantId)->count()])->all();
    }

    public function test_criar_monta_a_demo_isolada_com_25_equipamentos_e_4_problematicos(): void
    {
        $real = $this->tenantReal();
        $antes = $this->contagensDoTenant((string) $real->id);

        $r = app(LocadoraDemoService::class)->criar('SenhaDemo123');

        $demo = Tenant::withoutGlobalScopes()->where('slug', LocadoraDemoService::SLUG)->firstOrFail();
        $this->assertTrue($demo->is_demo);
        $this->assertSame('Locadora Demo', $demo->name);
        // 25 equipamentos de locacao + 6 veiculos da frota (criados por LocadoraDemoOperacional).
        $this->assertSame(31, DB::table('assets')->where('tenant_id', $demo->id)->count());
        $this->assertSame(25, DB::table('assets')->where('tenant_id', $demo->id)->where('is_vehicle', false)->count());
        $this->assertSame(25, $r['equipamentos']);
        $this->assertGreaterThan(100, DB::table('maintenance_orders')->where('tenant_id', $demo->id)->count());

        // Plano proprio da demo, com as chaves das telas novas ligadas so' nele.
        $plano = Plan::findOrFail($demo->plan_id);
        foreach (LocadoraDemoService::FEATURES_EXTRAS as $chave) {
            $this->assertTrue($plano->hasFeature($chave), $chave);
        }
        $this->assertTrue($plano->hasFeature('tabela_assets'));

        // Login do admin demo.
        $admin = User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->firstOrFail();
        $this->assertTrue(Hash::check('SenhaDemo123', $admin->password));
        $this->assertSame((string) $demo->id, (string) $admin->tenant_id);

        // 4 problematicos (custo acumulado alto vs. valor) e o resto controlado.
        $razoes = DB::table('assets as a')
            ->leftJoin('maintenance_orders as m', 'm.asset_id', '=', 'a.id')
            ->where('a.tenant_id', $demo->id)->groupBy('a.id', 'a.acquisition_value')
            ->selectRaw('coalesce(sum(m.total_order_cost),0) / a.acquisition_value as razao')->pluck('razao')->map(fn ($v) => (float) $v);
        $this->assertSame(4, $razoes->filter(fn ($v) => $v > 0.5)->count());
        $this->assertSame(0, $razoes->filter(fn ($v) => $v > 0.4 && $v <= 0.5)->count());

        // Custo da OS fecha: total = mao de obra + material + logistica.
        $this->assertSame(0, DB::table('maintenance_orders')->where('tenant_id', $demo->id)
            ->whereRaw('abs(total_order_cost - (labor_cost + material_cost + logistics_cost)) > 0.01')->count());

        // 13 equipamentos locados, cada um com contrato ativo e coordenadas da obra.
        $this->assertSame(13, DB::table('assets')->where('tenant_id', $demo->id)->where('status', 'locado')->count());
        $this->assertSame(13, DB::table('contracts')->where('tenant_id', $demo->id)->where('status', 'Ativo')->whereNotNull('latitude_obra')->count());

        // Nenhum dado do tenant real foi tocado.
        $this->assertSame($antes, $this->contagensDoTenant((string) $real->id));
    }

    public function test_recriar_e_idempotente_e_apagar_remove_tudo_sem_tocar_em_outro_tenant(): void
    {
        $real = $this->tenantReal();
        $antes = $this->contagensDoTenant((string) $real->id);
        $servico = app(LocadoraDemoService::class);

        $a = $servico->criar('Senha1');
        $b = $servico->criar('Senha2');
        $this->assertSame($a['custo_total'], $b['custo_total'], 'a demo e deterministica');
        $this->assertSame(1, Tenant::withoutGlobalScopes()->where('slug', LocadoraDemoService::SLUG)->count());
        $this->assertSame(1, Plan::where('name', LocadoraDemoService::PLANO_NOME)->count());

        $demoId = (string) $servico->tenant()->id;
        $r = $servico->apagar();

        $this->assertTrue($r['apagado']);
        $this->assertNull($servico->tenant());
        $this->assertSame(0, Plan::where('name', LocadoraDemoService::PLANO_NOME)->count());
        $this->assertFalse(User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->exists());
        foreach (Schema::getTables() as $t) {
            if (Schema::hasColumn($t['name'], 'tenant_id')) {
                $this->assertSame(0, DB::table($t['name'])->where('tenant_id', $demoId)->count(), 'sobrou linha em '.$t['name']);
            }
        }
        $this->assertSame($antes, $this->contagensDoTenant((string) $real->id));
        $this->assertFalse($servico->apagar()['apagado'], 'apagar de novo e inofensivo');
    }

    public function test_trava_recusa_mexer_em_tenant_de_mesmo_slug_que_nao_e_demo(): void
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $falso = Tenant::create(['name' => 'Locadora Demo', 'slug' => LocadoraDemoService::SLUG, 'plan_id' => $plano->id, 'status' => 'active']);
        Asset::create(['tenant_id' => $falso->id, 'name' => 'Ativo do cliente de verdade', 'tag' => 'V-'.uniqid(), 'status' => 'disponivel']);

        foreach (['criar', 'apagar'] as $metodo) {
            try {
                $metodo === 'criar' ? app(LocadoraDemoService::class)->criar('x') : app(LocadoraDemoService::class)->apagar();
                $this->fail("$metodo deveria recusar");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('NÃO está marcada como demo', $e->getMessage());
            }
        }

        $this->assertSame(1, DB::table('assets')->where('tenant_id', $falso->id)->count());
        $this->assertTrue(Tenant::withoutGlobalScopes()->whereKey($falso->id)->exists());
    }

    public function test_comandos_criam_e_apagam(): void
    {
        $this->artisan('demo:alec-criar', ['--senha' => 'SenhaCmd123'])->assertSuccessful();
        $this->assertNotNull(app(LocadoraDemoService::class)->tenant());

        $this->artisan('demo:alec-apagar', ['--force' => true])->assertSuccessful();
        $this->assertNull(app(LocadoraDemoService::class)->tenant());
    }

    public function test_contrato_da_demo_sobrevive_ao_saas_sync_modules_do_deploy(): void
    {
        app(LocadoraDemoService::class)->criar('x');
        $demo = app(LocadoraDemoService::class)->tenant();

        $this->artisan('saas:sync-modules')->assertSuccessful();

        $plano = Plan::findOrFail($demo->plan_id);
        $this->assertFalse(array_is_list($plano->features), 'contrato em formato de mapa, como o da Central');
        foreach (array_merge(['tabela_assets', 'tabela_contracts', 'tabela_maintenance_orders'], LocadoraDemoService::FEATURES_EXTRAS) as $chave) {
            $this->assertTrue($plano->hasFeature($chave), "{$chave} deve continuar ligada depois do sync de modulos");
        }
        $this->assertSame(0, app(LocadoraDemoService::class)->apagar()['apagado'] ? 0 : 1);
    }
}
