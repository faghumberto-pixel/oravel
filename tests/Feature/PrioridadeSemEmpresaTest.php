<?php

namespace Tests\Feature;

use App\Models\PriorityLevel;
use App\Services\Prioridade\PrioridadeDaOs;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Sem empresa ativa (id vazio) a lista de prioridades nao pode consultar nem gravar com tenant_id = '' (no Postgres, erro de uuid). */
class PrioridadeSemEmpresaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        app('db')->purge();
        Schema::create('priority_levels', function ($t) {
            $t->string('id')->primary();
            $t->string('tenant_id');
            $t->string('code');
            $t->string('name');
            $t->string('color')->nullable();
            $t->integer('sort_order')->default(0);
            $t->string('base_level')->nullable();
            $t->boolean('is_system')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function test_id_vazio_nao_grava_nem_devolve_niveis(): void
    {
        $this->assertSame([], PrioridadeDaOs::opcoes(''));
        $this->assertSame(0, PriorityLevel::withoutGlobalScopes()->count());
    }

    public function test_empresa_real_continua_ganhando_os_4_niveis_padrao(): void
    {
        $this->assertSame(['critica', 'alta', 'media', 'baixa'], array_keys(PrioridadeDaOs::opcoes('empresa-teste')));
    }
}
