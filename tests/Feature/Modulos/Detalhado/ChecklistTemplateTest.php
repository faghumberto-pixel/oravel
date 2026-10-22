<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\ChecklistTemplateResource;
use App\Models\ChecklistTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/** Checklists (Manutencao). */
class ChecklistTemplateTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return ChecklistTemplate::class;
    }

    protected function resource(): string
    {
        return ChecklistTemplateResource::class;
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        return $this->criar(ChecklistTemplate::class, ['tenant_id' => $tenant->id, 'name' => 'Checklist '.uniqid(), 'is_active' => true]);
    }

    protected function dadosDoFormulario(): array
    {
        return ['name' => 'Inspecao diaria de empilhadeira', 'description' => 'Antes do turno', 'is_active' => true];
    }

    protected function camposObrigatorios(): array
    {
        return ['name'];
    }
}
