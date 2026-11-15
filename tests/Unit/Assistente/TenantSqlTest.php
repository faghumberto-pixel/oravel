<?php

namespace Tests\Unit\Assistente;

use App\Support\Assistente\TenantSql;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Puro (nao sobe o app nem toca no banco): so valida o texto do SQL e a montagem das CTEs.
 * Rodar com: vendor/bin/phpunit tests/Unit/Assistente/TenantSqlTest.php
 */
class TenantSqlTest extends TestCase
{
    private const T = '0a1b2c3d-1111-4222-8333-444455556666';

    public static function consultasValidas(): array
    {
        return [
            'simples' => ['SELECT name, current_stock FROM materials WHERE current_stock < min_stock'],
            'join com alias' => ['SELECT a.patrimonio, m.name FROM parts_requests pr JOIN maintenance_orders mo ON mo.id = pr.maintenance_order_id JOIN assets a ON a.id = mo.asset_id JOIN materials m ON m.id = pr.material_id'],
            'ponto e virgula final' => ['SELECT id FROM suppliers;'],
            'texto com palavra proibida entre aspas' => ["SELECT id FROM materials WHERE name ILIKE '%update%' OR name ILIKE '%pg_%'"],
            'colunas current_*' => ['SELECT current_quantity FROM warehouse_stocks'],
            'date_part' => ["SELECT date_part('day', now() - created_at) FROM purchase_orders"],
            'extract nao vira tabela' => ['SELECT EXTRACT(epoch FROM created_at) FROM purchase_orders'],
            'group by' => ['SELECT status, COUNT(*) FROM purchase_orders GROUP BY status ORDER BY 2 DESC'],
            'with proprio' => ['WITH x AS (SELECT id FROM suppliers) SELECT * FROM x LIMIT 10'],
            'subselect' => ['SELECT name FROM materials WHERE supplier_id IN (SELECT id FROM suppliers WHERE name ILIKE \'%a%\')'],
        ];
    }

    #[DataProvider('consultasValidas')]
    public function test_aceita_consulta_valida(string $sql): void
    {
        [$final, $erro] = TenantSql::preparar(self::T, $sql);

        $this->assertNull($erro, (string) $erro);
        $this->assertNotNull($final);
        $this->assertStringStartsWith('WITH ', $final);
        $this->assertStringContainsString("tenant_id = '".self::T."'", $final);
    }

    public static function consultasProibidas(): array
    {
        return [
            'delete' => ['DELETE FROM materials'],
            'update' => ['UPDATE materials SET name = 1'],
            'drop' => ['DROP TABLE materials'],
            'duas consultas' => ['SELECT 1; SELECT 2'],
            'select into' => ['SELECT * INTO x FROM materials'],
            'tabela fora da lista' => ['SELECT * FROM users'],
            'tabela tenants' => ['SELECT * FROM tenants'],
            'join tabela fora da lista' => ['SELECT * FROM materials m JOIN users u ON u.id = m.id'],
            'schema qualificado' => ['SELECT * FROM public.materials'],
            'aspas duplas' => ['SELECT * FROM "materials"'],
            'comentario' => ['SELECT * FROM materials -- x'],
            'bloco de comentario' => ['SELECT * FROM materials /* x */'],
            'pg_sleep' => ['SELECT pg_sleep(10) FROM materials'],
            'pg_read_file' => ["SELECT pg_read_file('/etc/passwd') FROM materials"],
            'current_user' => ['SELECT current_user FROM materials'],
            'current_setting' => ["SELECT current_setting('x') FROM materials"],
            'information_schema' => ['SELECT * FROM information_schema.tables'],
            'virgula no from' => ['SELECT * FROM materials m, users u'],
            'virgula no from sem alias' => ['SELECT * FROM materials, users'],
            'for update' => ['SELECT * FROM materials FOR UPDATE'],
            'with recursive' => ['WITH RECURSIVE r AS (SELECT 1) SELECT * FROM r'],
            'cte da ia lendo tabela fora da lista' => ['WITH x AS (SELECT * FROM users) SELECT * FROM x'],
            'limit acima do maximo no with' => ['WITH x AS (SELECT id FROM suppliers) SELECT * FROM x LIMIT 100000'],
            'vazia' => ['   '],
            'copy' => ["COPY materials TO '/tmp/x'"],
            'set role' => ['SET ROLE postgres'],
            'dblink' => ["SELECT * FROM dblink('x','y') AS t(a int)"],
        ];
    }

    #[DataProvider('consultasProibidas')]
    public function test_barra_consulta_perigosa(string $sql): void
    {
        [$final, $erro] = TenantSql::preparar(self::T, $sql);

        $this->assertNull($final, 'deveria barrar: '.$sql);
        $this->assertNotEmpty($erro);
    }

    public function test_toda_tabela_tem_filtro_de_cliente_nas_ctes(): void
    {
        $ctes = TenantSql::ctes(self::T);

        foreach (TenantSql::TABELAS as $tabela => $def) {
            $this->assertStringContainsString("{$tabela} AS (SELECT", $ctes, "falta CTE de {$tabela}");
        }

        // cada CTE filtra por tenant (direto ou pelo pai)
        $this->assertSame(count(TenantSql::TABELAS), substr_count($ctes, ' AS (SELECT '));
        $this->assertSame(count(TenantSql::TABELAS), substr_count($ctes, "tenant_id = '".self::T."'"));
    }

    public function test_filhas_vem_antes_dos_pais_nas_ctes(): void
    {
        $ctes = TenantSql::ctes(self::T);

        $this->assertLessThan(strpos($ctes, 'warehouses AS ('), strpos($ctes, 'warehouse_stocks AS ('));
        $this->assertLessThan(strpos($ctes, 'material_requests AS ('), strpos($ctes, 'material_request_items AS ('));
    }

    public function test_colunas_sensiveis_nao_sao_expostas(): void
    {
        $ctes = TenantSql::ctes(self::T);

        foreach (['bank_account_pix', 'cnpj_card', 'contrato_social', 'cnd_federal', 'crf_fgts', 'cndt', 'compliance_ceis_cnep', 'password', 'remember_token'] as $col) {
            $this->assertStringNotContainsString($col, $ctes);
        }
    }

    public function test_limite_de_linhas_por_fora(): void
    {
        [$final] = TenantSql::preparar(self::T, 'SELECT id FROM suppliers');

        $this->assertStringEndsWith('LIMIT '.TenantSql::MAX_LINHAS, $final);
    }

    public function test_tenant_id_so_aceita_uuid(): void
    {
        foreach (['1', '1 OR 1=1', "x'; DROP TABLE users;--", '', '0a1b2c3d-1111-4222-8333-44445555666', "0a1b2c3d-1111-4222-8333-444455556666'"] as $ruim) {
            [$final, $erro] = TenantSql::preparar($ruim, 'SELECT id FROM suppliers');
            $this->assertNull($final, "deveria recusar tenant: {$ruim}");
            $this->assertSame('cliente invalido', $erro);
        }
    }
}
