<?php

namespace App\Filament\Central\Resources\ContratoResource\Pages;

use App\Filament\Central\Resources\ContratoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContrato extends EditRecord
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Espelho de CreateContrato::mutateFormDataBeforeCreate() -- mescla os
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
     * Sentido contrário: ao abrir o contrato pra editar, distribui o
     * 'features' salvo (array único) de volta pros campos
     * 'features_group_{grupo}' que o formulário realmente usa -- sem
     * isso, os checkboxes por grupo sempre abririam todos desmarcados,
     * mesmo com módulos já selecionados.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $selected = collect($data['features'] ?? []);

        foreach (ContratoResource::groupedFeatureOptions() as $groupName => $options) {
            $keys = array_keys($options);
            $data[ContratoResource::groupFieldName($groupName)] = $selected->intersect($keys)->values()->all();
        }

        return $data;
    }
}
