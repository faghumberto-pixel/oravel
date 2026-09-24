<?php

namespace App\Filament\Central\Resources\PropostaResource\Pages;

use App\Filament\Central\Resources\PropostaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProposta extends EditRecord
{
    protected static string $resource = PropostaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Espelho de CreateProposta::mutateFormDataBeforeCreate() -- mescla os
     * campos por grupo de volta num único 'features' antes de salvar.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['price'] = $data['base_price'] ?? 0;

        $features = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'features_group_') && is_array($value)) {
                $features = array_merge($features, $value);
            }
        }
        $data['features'] = array_values(array_unique($features));

        return array_filter($data, fn ($key) => ! str_starts_with($key, 'features_group_'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Sentido contrário: ao abrir a proposta pra editar, distribui o
     * 'features' salvo (array único) de volta pros campos
     * 'features_group_{grupo}' que o formulário realmente usa -- sem
     * isso, os checkboxes por grupo sempre abririam todos desmarcados,
     * mesmo com módulos já selecionados.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $selected = collect($data['features'] ?? []);

        foreach (PropostaResource::groupedFeatureOptions() as $groupName => $options) {
            $keys = array_keys($options);
            $data[PropostaResource::groupFieldName($groupName)] = $selected->intersect($keys)->values()->all();
        }

        return $data;
    }
}
