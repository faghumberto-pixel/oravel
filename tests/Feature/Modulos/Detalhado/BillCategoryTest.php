<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\BillCategoryResource;
use App\Models\BillCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/** Categorias de Contas a Pagar (Financeiro). */
class BillCategoryTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return BillCategory::class;
    }

    protected function resource(): string
    {
        return BillCategoryResource::class;
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        return $this->criar(BillCategory::class, ['tenant_id' => $tenant->id, 'name' => 'Categoria '.uniqid()]);
    }

    protected function dadosDoFormulario(): array
    {
        return ['name' => 'Combustivel'];
    }

    protected function camposObrigatorios(): array
    {
        return ['name'];
    }
}
