<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\BranchResource;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/** Filiais (Configuracoes). */
class BranchTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return Branch::class;
    }

    protected function resource(): string
    {
        return BranchResource::class;
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        return $this->criar(Branch::class, ['tenant_id' => $tenant->id, 'name' => 'Filial '.uniqid()]);
    }

    protected function dadosDoFormulario(): array
    {
        return ['name' => 'Filial Campinas', 'description' => 'Unidade do interior'];
    }

    protected function camposObrigatorios(): array
    {
        return ['name'];
    }
}
