<?php

namespace App\Filament\Resources\CrmPipelineResource\Pages;

use App\Filament\Resources\CrmPipelineResource;
use App\Models\CrmEtapa;
use App\Models\CrmPipeline;
use Filament\Resources\Pages\CreateRecord;

class CreateCrmPipeline extends CreateRecord
{
    protected static string $resource = CrmPipelineResource::class;

    /** Funil novo já nasce com as 3 etapas mínimas (aberta, ganho e perdido) para poder ser usado. */
    protected function afterCreate(): void
    {
        /** @var CrmPipeline $funil */
        $funil = $this->record;

        foreach ([['Novo', 'novo', 10, CrmEtapa::TIPO_ABERTA, 'blue'], ['Ganho', 'ganho', 100, CrmEtapa::TIPO_GANHO, 'emerald'], ['Perdido', 'perdido', 0, CrmEtapa::TIPO_PERDIDO, 'red']] as $i => [$nome, $slug, $prob, $tipo, $cor]) {
            CrmEtapa::create([
                'tenant_id' => $funil->tenant_id, 'crm_funil_id' => $funil->id, 'nome' => $nome, 'slug' => $slug,
                'ordem' => $i, 'probabilidade' => $prob, 'tipo' => $tipo, 'cor' => $cor,
            ]);
        }
    }
}
