<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\CostCenterResource;
use App\Models\CostCenter;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/** Centros de Custo (Configuracoes). */
class CostCenterTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return CostCenter::class;
    }

    protected function resource(): string
    {
        return CostCenterResource::class;
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        return $this->criar(CostCenter::class, ['tenant_id' => $tenant->id, 'name' => 'CC '.uniqid(), 'code' => strtoupper(substr(uniqid(), -5))]);
    }

    protected function dadosDoFormulario(): array
    {
        return ['name' => 'Manutencao Pesada', 'code' => 'MP-01'];
    }

    protected function camposObrigatorios(): array
    {
        return ['name'];
    }
}
