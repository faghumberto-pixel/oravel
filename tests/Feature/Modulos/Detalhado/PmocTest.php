<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\PmocResource;
use App\Models\Pmoc;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/** PMOC - Plano de Manutencao, Operacao e Controle de climatizacao (Equipe). */
class PmocTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return Pmoc::class;
    }

    protected function resource(): string
    {
        return PmocResource::class;
    }

    private function atributos(): array
    {
        return [
            'titulo' => 'PMOC Predio A', 'local_ambiente' => 'Sala de servidores', 'tipo_sistema' => 'split',
            'responsavel_nome' => 'Maria Silva', 'data_inicio_vigencia' => '2026-01-01', 'frequencia_dias' => 30,
            'procedimentos_limpeza' => 'Limpar filtros', 'procedimentos_filtros' => 'Trocar a cada 90 dias',
            'procedimentos_inspecao' => 'Inspecao visual', 'procedimentos_medicao' => 'Medir temperatura', 'status' => 'ativo',
        ];
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        return $this->criar(Pmoc::class, ['tenant_id' => $tenant->id] + $this->atributos());
    }

    protected function dadosDoFormulario(): array
    {
        return $this->atributos();
    }

    protected function camposObrigatorios(): array
    {
        return [
            'titulo', 'local_ambiente', 'tipo_sistema', 'responsavel_nome', 'data_inicio_vigencia', 'frequencia_dias',
            'procedimentos_limpeza', 'procedimentos_filtros', 'procedimentos_inspecao', 'procedimentos_medicao',
        ];
    }

    public function test_formulario_grava_os_campos_informados(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('create'))
            ->fillForm($this->atributos() + ['responsavel_email' => 'maria@empresa.com.br', 'temperatura_ideal_min' => 22, 'temperatura_ideal_max' => 24])
            ->call('create')
            ->assertHasNoFormErrors();

        $pmoc = Pmoc::withoutGlobalScopes()->where('titulo', 'PMOC Predio A')->sole();
        $this->assertSame('split', $pmoc->tipo_sistema);
        $this->assertSame('maria@empresa.com.br', $pmoc->responsavel_email);
        $this->assertSame(30, (int) $pmoc->frequencia_dias);
    }

    public function test_rejeita_email_do_responsavel_invalido(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('create'))
            ->fillForm($this->atributos() + ['responsavel_email' => 'isto-nao-e-email'])
            ->call('create')
            ->assertHasFormErrors(['responsavel_email']);
    }

    public function test_edita_o_status_do_pmoc(): void
    {
        $pmoc = $this->novoRegistro($this->tenantA);
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('edit'), ['record' => $pmoc->getKey()])
            ->fillForm(['status' => 'inativo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('inativo', $pmoc->fresh()->status);
    }
}
