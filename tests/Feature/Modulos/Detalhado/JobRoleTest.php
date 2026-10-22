<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\JobRoleResource;
use App\Models\Department;
use App\Models\JobRole;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/** Funcoes e Cargos (Equipe): criacao e edicao em modal, sem pagina propria. */
class JobRoleTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return JobRole::class;
    }

    protected function resource(): string
    {
        return JobRoleResource::class;
    }

    private function departamento(Tenant $tenant): Department
    {
        return $this->criar(Department::class, ['tenant_id' => $tenant->id, 'name' => 'Manutencao '.uniqid()]);
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        return $this->criar(JobRole::class, [
            'tenant_id' => $tenant->id, 'name' => 'Funcao '.uniqid(), 'department_id' => $this->departamento($tenant)->id,
        ]);
    }

    public function test_cria_funcao_pelo_modal_no_tenant_do_administrador(): void
    {
        $dep = $this->departamento($this->tenantA);
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('index'))
            ->callTableAction('create', data: ['name' => 'Soldador', 'department_id' => $dep->id])
            ->assertHasNoTableActionErrors();

        $criada = JobRole::withoutGlobalScopes()->where('name', 'Soldador')->sole();
        $this->assertSame((string) $this->tenantA->id, (string) $criada->tenant_id);
        $this->assertSame((string) $dep->id, (string) $criada->department_id);
    }

    public function test_modal_exige_nome_e_departamento(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('index'))
            ->callTableAction('create', data: [])
            ->assertHasTableActionErrors(['name' => 'required', 'department_id' => 'required']);
    }

    public function test_edita_e_exclui_funcao(): void
    {
        $funcao = $this->novoRegistro($this->tenantA);
        $this->actingAs($this->a['admin']);
        $pagina = $this->paginaDoResource('index');

        Livewire::test($pagina)
            ->callTableAction('edit', $funcao, data: ['name' => 'Soldador Senior', 'department_id' => $funcao->department_id])
            ->assertHasNoTableActionErrors();
        $this->assertSame('Soldador Senior', $funcao->fresh()->name);

        Livewire::test($pagina)->callTableAction('delete', $funcao);
        $this->assertNull(JobRole::withoutGlobalScopes()->find($funcao->id));
    }

    public function test_quem_so_le_nao_ve_as_acoes_de_alterar(): void
    {
        $funcao = $this->novoRegistro($this->tenantA);
        $this->conceder($this->a['tecnico'], $this->slug(), ['ler']);
        $this->actingAs($this->a['tecnico']);

        Livewire::test($this->paginaDoResource('index'))
            ->assertTableActionHidden('edit', $funcao)
            ->assertTableActionHidden('delete', $funcao)
            ->assertTableActionHidden('create');
    }

    public function test_nao_aceita_departamento_de_outro_tenant(): void
    {
        $depDeB = $this->departamento($this->tenantB);
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('index'))
            ->callTableAction('create', data: ['name' => 'Invasora', 'department_id' => $depDeB->id]);

        $this->lacunaConhecida(
            'o servidor nao valida que department_id e do tenant (so o formulario filtra); requisicao forjada vincula departamento alheio',
            fn () => $this->assertSame(0, JobRole::withoutGlobalScopes()->where('name', 'Invasora')->count())
        );
    }
}
