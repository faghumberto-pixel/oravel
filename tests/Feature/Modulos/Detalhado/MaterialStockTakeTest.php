<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\MaterialStockTakeResource;
use App\Models\InternalUnit;
use App\Models\MaterialStockTake;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/** Inventarios de estoque (Materiais e Pecas). */
class MaterialStockTakeTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return MaterialStockTake::class;
    }

    protected function resource(): string
    {
        return MaterialStockTakeResource::class;
    }

    private function unidade(Tenant $tenant): InternalUnit
    {
        return $this->criar(InternalUnit::class, ['tenant_id' => $tenant->id, 'name' => 'Almoxarifado '.uniqid()]);
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        $dono = $tenant->is($this->tenantA) ? $this->a['admin'] : $this->b['admin'];

        return $this->criar(MaterialStockTake::class, [
            'tenant_id' => $tenant->id, 'internal_unit_id' => $this->unidade($tenant)->id, 'conducted_by_user_id' => $dono->id,
        ]);
    }

    protected function dadosDoFormulario(): array
    {
        return [
            'internal_unit_id' => $this->unidade($this->tenantA)->id,
            'conducted_by_user_id' => $this->a['admin']->id,
            'notes' => 'Contagem de fim de mes',
        ];
    }

    protected function camposObrigatorios(): array
    {
        return ['internal_unit_id'];
    }
}
