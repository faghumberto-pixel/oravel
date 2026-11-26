<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\HorimeterReading;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\GestaoAVistaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Completa a Gestão à Vista da empresa demo (causas de falha, paradas e horímetro): só adiciona e desfaz só o que fez. */
class DemoPopularGestaoAVistaTest extends TestCase
{
    use DatabaseTransactions;

    private string $nome;

    protected function setUp(): void
    {
        parent::setUp();
        File::delete(storage_path('app/demo-gav-ids.json'));
        $this->nome = 'Demo GAV '.uniqid();
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/demo-gav-ids.json'));
        parent::tearDown();
    }

    private function paradas(Tenant $t): int
    {
        return AssetDowntimeEvent::withoutGlobalScopes()->where('tenant_id', $t->id)->count();
    }

    private function empresa(string $nome, int $corretivas = 3): Tenant
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_maintenance_orders' => true, 'tabela_assets' => true]]);
        $tenant = Tenant::create(['name' => $nome, 'slug' => 'gav-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        for ($i = 0; $i < $corretivas; $i++) {
            $ativo = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo '.uniqid(), 'status' => 'disponivel']);
            MaintenanceOrder::create(['tenant_id' => $tenant->id, 'asset_id' => $ativo->id, 'status' => 'Concluída', 'maintenance_type' => 'Corretiva', 'description' => 'Teste', 'scheduled_at' => now()->subDays(10 + $i)]);
        }

        return $tenant;
    }

    public function test_completa_causas_paradas_e_horimetro_e_os_indicadores_passam_a_ter_numero(): void
    {
        $t = $this->empresa($this->nome);
        $outra = $this->empresa('Outra '.uniqid());
        $antes = $this->paradas($t);
        $antesOutra = $this->paradas($outra);

        $this->assertSame(0, Artisan::call('demo:popular-gestao-a-vista', ['--tenant' => $this->nome]));

        $this->assertSame(0, MaintenanceOrder::withoutGlobalScopes()->where('tenant_id', $t->id)->whereNull('failure_category')->count(), 'todas as corretivas ganharam causa');
        $this->assertGreaterThanOrEqual($antes + 6, $this->paradas($t), 'pelo menos 2 paradas novas por ativo');
        $this->assertSame(6, HorimeterReading::withoutGlobalScopes()->where('tenant_id', $t->id)->count(), '2 leituras por ativo');
        $this->assertSame($antesOutra, $this->paradas($outra), 'a outra empresa não recebe nada');

        $filtros = ['from' => now()->subMonths(6)->toDateString(), 'until' => now()->toDateString(), 'branchId' => null, 'assetId' => null];
        $servico = new GestaoAVistaService($t->id);
        $this->assertNotEmpty($servico->principaisCausasFalha($filtros));
        $this->assertGreaterThan(0, $servico->tempoParadaNaoPlanejada($filtros)['valor_horas']);
        $this->assertNotNull($servico->mtbf($filtros)['valor_horas'] ?? null);
    }

    public function test_nao_roda_duas_vezes_e_recusa_empresa_sem_corretivas(): void
    {
        $t = $this->empresa($this->nome);
        $this->assertSame(0, Artisan::call('demo:popular-gestao-a-vista', ['--tenant' => $this->nome]));
        $paradas = $this->paradas($t);

        $this->assertSame(1, Artisan::call('demo:popular-gestao-a-vista', ['--tenant' => $this->nome]));
        $this->assertSame($paradas, $this->paradas($t));

        File::delete(storage_path('app/demo-gav-ids.json'));
        $vazia = 'Vazia '.uniqid();
        $this->empresa($vazia, 0);
        $this->assertSame(1, Artisan::call('demo:popular-gestao-a-vista', ['--tenant' => $vazia]));
    }

    public function test_remover_desfaz_so_o_que_o_comando_fez_e_preserva_causa_ja_preenchida(): void
    {
        $t = $this->empresa($this->nome);
        $antes = $this->paradas($t);
        $propria = MaintenanceOrder::withoutGlobalScopes()->where('tenant_id', $t->id)->first();
        DB::table('maintenance_orders')->where('id', $propria->id)->update(['failure_category' => 'estrutural']);

        $this->assertSame(0, Artisan::call('demo:popular-gestao-a-vista', ['--tenant' => $this->nome]));
        $this->assertSame('estrutural', $propria->fresh()->failure_category, 'causa já preenchida nunca é trocada');

        $this->assertSame(0, Artisan::call('demo:popular-gestao-a-vista', ['--tenant' => $this->nome, '--remover' => true]));

        $this->assertSame($antes, $this->paradas($t), 'só as paradas do comando saem; as que o sistema criou ficam');
        $this->assertSame(0, HorimeterReading::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
        $this->assertSame('estrutural', $propria->fresh()->failure_category, 'a causa que já existia continua');
        $this->assertSame(2, MaintenanceOrder::withoutGlobalScopes()->where('tenant_id', $t->id)->whereNull('failure_category')->count(), 'as demais voltam a ficar sem causa');
    }
}
