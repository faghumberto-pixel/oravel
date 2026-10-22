<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\BillingPlanResource;
use App\Models\BillingPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/** Planos de Cobranca (Financeiro): modal, sem pagina propria. */
class BillingPlanTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return BillingPlan::class;
    }

    protected function resource(): string
    {
        return BillingPlanResource::class;
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        return $this->criar(BillingPlan::class, [
            'tenant_id' => $tenant->id, 'amount' => 1500, 'frequency' => BillingPlan::FREQUENCY_MONTHLY, 'due_day' => 10, 'active' => true,
        ]);
    }

    public function test_cria_plano_pelo_modal_no_tenant_do_administrador(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test($this->paginaDoResource('index'))
            ->callTableAction('create', data: ['frequency' => BillingPlan::FREQUENCY_MONTHLY, 'amount' => 2500, 'due_day' => 15, 'active' => true])
            ->assertHasNoTableActionErrors();

        $plano = BillingPlan::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->where('due_day', 15)->sole();
        $this->assertEquals(2500, $plano->amount);
    }

    public function test_exige_valor_e_dia_de_vencimento_entre_1_e_31(): void
    {
        $this->actingAs($this->a['admin']);
        $pagina = $this->paginaDoResource('index');

        Livewire::test($pagina)
            ->callTableAction('create', data: ['frequency' => BillingPlan::FREQUENCY_MONTHLY, 'amount' => null, 'due_day' => null])
            ->assertHasTableActionErrors(['amount' => 'required', 'due_day' => 'required']);

        foreach ([0, 32] as $dia) {
            Livewire::test($pagina)
                ->callTableAction('create', data: ['frequency' => BillingPlan::FREQUENCY_MONTHLY, 'amount' => 100, 'due_day' => $dia])
                ->assertHasTableActionErrors(['due_day']);
        }
    }

    public function test_edita_e_exclui_plano(): void
    {
        $plano = $this->novoRegistro($this->tenantA);
        $this->actingAs($this->a['admin']);
        $pagina = $this->paginaDoResource('index');

        Livewire::test($pagina)
            ->callTableAction('edit', $plano, data: ['frequency' => BillingPlan::FREQUENCY_MONTHLY, 'amount' => 1800, 'due_day' => 5, 'active' => false])
            ->assertHasNoTableActionErrors();
        $plano->refresh();
        $this->assertEquals(1800, $plano->amount);
        $this->assertFalse((bool) $plano->active);

        Livewire::test($pagina)->callTableAction('delete', $plano);
        $this->assertNull(BillingPlan::withoutGlobalScopes()->find($plano->id));
    }

    public function test_quem_so_le_nao_ve_as_acoes_de_alterar(): void
    {
        $plano = $this->novoRegistro($this->tenantA);
        $this->conceder($this->a['tecnico'], $this->slug(), ['ler']);
        $this->actingAs($this->a['tecnico']);

        Livewire::test($this->paginaDoResource('index'))
            ->assertTableActionHidden('edit', $plano)
            ->assertTableActionHidden('delete', $plano)
            ->assertTableActionHidden('create');
    }
}
