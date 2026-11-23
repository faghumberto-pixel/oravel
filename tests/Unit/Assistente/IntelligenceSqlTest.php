<?php

namespace Tests\Unit\Assistente;

use App\Services\IntelligenceChatService;
use App\Support\Assistente\TenantSql;
use PHPUnit\Framework\TestCase;

/**
 * Puro (sem app/banco): o agente do Intelligence so enxerga a camada de inteligencia, isolada por empresa.
 * vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/Assistente/IntelligenceSqlTest.php
 */
class IntelligenceSqlTest extends TestCase
{
    private const T = '0a1b2c3d-1111-4222-8333-444455556666';

    private function preparar(string $sql): array
    {
        return TenantSql::preparar(self::T, $sql, IntelligenceChatService::TABELAS);
    }

    public function test_aceita_consulta_de_evolucao_e_de_benchmark(): void
    {
        [$sql, $erro] = $this->preparar("SELECT s.data_referencia, i.valor FROM intelligence_indicators i JOIN intelligence_snapshots s ON s.id = i.snapshot_id WHERE i.chave = 'disponibilidade_frota' ORDER BY s.data_referencia");
        $this->assertNull($erro);
        $this->assertNotNull($sql);

        [, $erro] = $this->preparar("SELECT b.mediana FROM intelligence_benchmarks b JOIN intelligence_snapshots s ON b.porte = s.dados->>'porte' WHERE b.indicador = 'utilizacao_frota'");
        $this->assertNull($erro);
    }

    public function test_nao_enxerga_nenhuma_tabela_do_erp(): void
    {
        foreach (['assets', 'clients', 'maintenance_orders', 'contracts', 'users', 'tenants', 'plans', 'intelligence_imports'] as $tabela) {
            [$sql, $erro] = $this->preparar("SELECT * FROM {$tabela}");
            $this->assertNull($sql, $tabela);
            $this->assertStringContainsString('nao permitida', (string) $erro, $tabela);
        }
        // e o contrario: o chat de Suprimentos (lista padrao) nao enxerga as tabelas do Intelligence
        [$sql] = TenantSql::preparar(self::T, 'SELECT * FROM intelligence_snapshots');
        $this->assertNull($sql);
    }

    public function test_tabelas_da_empresa_filtram_por_tenant_e_o_benchmark_nao(): void
    {
        $ctes = TenantSql::ctes(self::T, IntelligenceChatService::TABELAS);

        foreach (['intelligence_snapshots', 'intelligence_indicators', 'intelligence_diagnostics'] as $t) {
            $this->assertStringContainsString("{$t} AS (SELECT", $ctes);
            $this->assertMatchesRegularExpression("/{$t} AS \\(SELECT [^)]* FROM {$t} WHERE tenant_id = '".self::T."'\\)/", $ctes);
        }
        $this->assertMatchesRegularExpression('/intelligence_benchmarks AS \(SELECT [^)]* FROM intelligence_benchmarks WHERE TRUE\)/', $ctes);
        // o benchmark nunca expoe tenant
        $this->assertStringNotContainsString('tenant_id', IntelligenceChatService::TABELAS['intelligence_benchmarks']['desc']);
        $this->assertNotContains('tenant_id', IntelligenceChatService::TABELAS['intelligence_benchmarks']['cols']);
    }

    public function test_continua_barrando_escrita(): void
    {
        foreach (['DELETE FROM intelligence_snapshots', 'UPDATE intelligence_indicators SET valor = 1', 'SELECT 1; DROP TABLE x'] as $sql) {
            [$final] = $this->preparar($sql);
            $this->assertNull($final, $sql);
        }
    }

    public function test_enxerga_as_duas_tabelas_de_equipamentos_isoladas_por_empresa(): void
    {
        [$sql, $erro] = $this->preparar("SELECT e.patrimonio, a.classificacao, a.motivo FROM intelligence_equipamentos e JOIN intelligence_equipamento_analises a ON a.equipamento_id = e.id WHERE a.classificacao = 'trocar_vender'");
        $this->assertNull($erro);
        $this->assertNotNull($sql);

        $ctes = TenantSql::ctes(self::T, IntelligenceChatService::TABELAS);
        foreach (['intelligence_equipamentos', 'intelligence_equipamento_analises'] as $t) {
            $this->assertMatchesRegularExpression("/{$t} AS \\(SELECT [^)]* FROM {$t} WHERE tenant_id = '".self::T."'\\)/", $ctes, $t);
        }
        // nada de tabela do ERP, nem a tabela de importações, mesmo com os equipamentos liberados
        foreach (['assets', 'maintenance_orders', 'intelligence_imports'] as $t) {
            [$final] = $this->preparar("SELECT * FROM {$t}");
            $this->assertNull($final, $t);
        }
    }
}
