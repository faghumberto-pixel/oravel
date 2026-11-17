<?php

namespace Tests\Feature;

use App\Models\MaintenanceOrder;
use App\Services\AnthropicApiClient;
use App\Services\MaintenanceOrderAnalysisService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** A análise de IA da O.S. recebe o vínculo de retrabalho nos dois sentidos (original → retornos e retorno → original). */
class AnaliseOsVinculoRetornoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        app('db')->purge();
        Schema::create('maintenance_orders', function ($t) {
            $t->string('id')->primary();
            $t->string('tenant_id')->nullable();
            $t->string('os_number')->nullable();
            $t->text('description')->nullable();
            $t->string('parent_os_id')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        DB::table('maintenance_orders')->insert([
            ['id' => 'a', 'tenant_id' => 't1', 'os_number' => 'OS-014', 'description' => 'Vazamento no cilindro', 'parent_os_id' => null, 'created_at' => '2026-10-12 09:00:00', 'finished_at' => '2026-10-14 16:00:00'],
            ['id' => 'b', 'tenant_id' => 't1', 'os_number' => 'OS-015', 'description' => 'Troca de vedações do cilindro', 'parent_os_id' => 'a', 'created_at' => '2026-10-23 08:00:00', 'finished_at' => null],
            ['id' => 'c', 'tenant_id' => 't2', 'os_number' => 'OS-OUTRA', 'description' => 'Outra empresa', 'parent_os_id' => 'a', 'created_at' => '2026-10-24 08:00:00', 'finished_at' => null],
        ]);
    }

    private function servico(): MaintenanceOrderAnalysisService
    {
        return new MaintenanceOrderAnalysisService(new AnthropicApiClient);
    }

    public function test_original_enxerga_os_que_voltaram_a_ela_so_da_propria_empresa(): void
    {
        $v = $this->servico()->vinculosDeRetorno(MaintenanceOrder::withoutGlobalScopes()->find('a'));

        $this->assertNull($v['retorno_de']);
        $this->assertCount(1, $v['retornos_posteriores']);
        $this->assertSame('OS-015', $v['retornos_posteriores'][0]['os']);
        $this->assertSame('23/10/2026', $v['retornos_posteriores'][0]['aberta_em']);
    }

    public function test_retorno_enxerga_a_os_original(): void
    {
        $v = $this->servico()->vinculosDeRetorno(MaintenanceOrder::withoutGlobalScopes()->find('b'));

        $this->assertSame('OS-014', $v['retorno_de']['os']);
        $this->assertSame('14/10/2026', $v['retorno_de']['concluida_em']);
        $this->assertSame([], $v['retornos_posteriores']);
    }
}
