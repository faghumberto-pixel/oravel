<?php

namespace App\Filament\Central\Resources\PlanResource\Pages;

use App\Filament\Central\Resources\PlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Planos antigos gravaram 'features' como dicionario {chave: true, ...}
     * (formato que Tenant::hasFeature() ainda entende via array_key_exists()).
     * O CheckboxList do Filament, porem, espera uma lista simples das chaves
     * selecionadas (['chave1', 'chave2']) -- sem essa conversao, in_array()
     * faz comparacao frouxa contra puros valores `true`, e qualquer string
     * nao-vazia "bate" com `true`, fazendo TODAS as opcoes aparecerem
     * marcadas e o toggle de uma afetar todas. So converte se realmente for
     * o formato antigo (dicionario); planos ja salvos apos este fix ja vem
     * como lista simples e passam direto.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['features']) && is_array($data['features']) && ! array_is_list($data['features'])) {
            $data['features'] = collect($data['features'])
                ->filter(fn ($value) => $value === true || $value === 1 || $value === '1' || $value === 'true')
                ->keys()
                ->values()
                ->all();
        }

        return $data;
    }
}
