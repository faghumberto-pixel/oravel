<?php

namespace Tests\Feature;

use App\Models\AIAnalysis;
use App\Models\User;
use Tests\TestCase;

/** Usuário comum de empresa (não super admin) consulta as análises de IA: o filtro de empresa não pode quebrar (erro 500 na tela da O.S.). */
class AnaliseIaEscopoDeEmpresaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        app('db')->purge();
    }

    public function test_usuario_de_empresa_consulta_analises_sem_erro_e_filtra_pela_empresa(): void
    {
        $this->actingAs(new User(['name' => 'Tecnico', 'email' => 'tecnico@empresa.test', 'tenant_id' => 'empresa-1']));

        $sql = AIAnalysis::query()->toSql();

        $this->assertStringContainsString('"ai_analyses"."tenant_id" = ?', $sql);
        $this->assertStringContainsString('"type" != ?', $sql);
    }
}
