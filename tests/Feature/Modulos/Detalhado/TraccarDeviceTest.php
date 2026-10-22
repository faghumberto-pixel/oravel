<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\TraccarDeviceResource;
use App\Models\Tenant;
use App\Models\TraccarDevice;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/** Vinculos de Rastreamento / Device GPS (Logistica): modal, sem pagina propria. */
class TraccarDeviceTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return TraccarDevice::class;
    }

    protected function resource(): string
    {
        return TraccarDeviceResource::class;
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        $user = $tenant->is($this->tenantA) ? $this->a['tecnico'] : $this->b['tecnico'];

        return $this->criar(TraccarDevice::class, [
            'tenant_id' => $tenant->id, 'user_id' => $user->id,
            'identifier' => 'IMEI-'.uniqid(), 'traccar_device_id' => random_int(1000, 999999),
        ]);
    }

    public function test_vincula_aparelho_a_um_usuario_do_proprio_tenant(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('index'))
            ->callTableAction('create', data: [
                'user_id' => $this->a['tecnico']->id, 'identifier' => 'TEC-CAMPINAS-01', 'traccar_device_id' => 42,
            ])
            ->assertHasNoTableActionErrors();

        $vinculo = TraccarDevice::withoutGlobalScopes()->where('identifier', 'TEC-CAMPINAS-01')->sole();
        $this->assertSame((string) $this->tenantA->id, (string) $vinculo->tenant_id);
        $this->assertSame((string) $this->a['tecnico']->id, (string) $vinculo->user_id);
    }

    public function test_exige_usuario_identificador_e_id_do_traccar(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('index'))
            ->callTableAction('create', data: [])
            ->assertHasTableActionErrors(['user_id' => 'required', 'identifier' => 'required', 'traccar_device_id' => 'required']);
    }

    public function test_id_do_traccar_precisa_ser_numerico(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('index'))
            ->callTableAction('create', data: ['user_id' => $this->a['tecnico']->id, 'identifier' => 'X', 'traccar_device_id' => 'abc'])
            ->assertHasTableActionErrors(['traccar_device_id']);
    }

    public function test_nao_vincula_a_usuario_de_outro_tenant(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('index'))
            ->callTableAction('create', data: ['user_id' => $this->b['tecnico']->id, 'identifier' => 'INVASOR', 'traccar_device_id' => 7])
            ->assertHasTableActionErrors(['user_id']);

        $this->assertSame(0, TraccarDevice::withoutGlobalScopes()->where('identifier', 'INVASOR')->count());
    }

    public function test_edita_e_exclui_vinculo(): void
    {
        $vinculo = $this->novoRegistro($this->tenantA);
        $this->actingAs($this->a['admin']);
        $pagina = $this->paginaDoResource('index');

        Livewire::test($pagina)
            ->callTableAction('edit', $vinculo, data: ['user_id' => $vinculo->user_id, 'identifier' => 'NOVO-ID', 'traccar_device_id' => 99])
            ->assertHasNoTableActionErrors();
        $this->assertSame('NOVO-ID', $vinculo->fresh()->identifier);

        Livewire::test($pagina)->callTableAction('delete', $vinculo);
        $this->assertNull(TraccarDevice::withoutGlobalScopes()->find($vinculo->id));
    }

    public function test_quem_so_le_nao_ve_as_acoes_de_alterar(): void
    {
        $vinculo = $this->novoRegistro($this->tenantA);
        $this->conceder($this->a['colaborador'], $this->slug(), ['ler']);
        $this->actingAs($this->a['colaborador']);

        Livewire::test($this->paginaDoResource('index'))
            ->assertTableActionHidden('edit', $vinculo)
            ->assertTableActionHidden('delete', $vinculo)
            ->assertTableActionHidden('create');
    }
}
