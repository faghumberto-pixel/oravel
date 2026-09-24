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
        $data['features'] = CreateContrato::mergeGroupedFeatures($data);

        return array_filter($data, fn ($key) => ! str_starts_with($key, 'features_group_'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Sentido contrário: ao abrir o contrato pra editar, distribui o
     * 'features' salvo (mapa `key => true`) de volta pros campos
     * 'features_group_{grupo}' que o formulário realmente usa -- sem
     * isso, os checkboxes por grupo sempre abririam todos desmarcados,
     * mesmo com módulos já selecionados. Aceita também o formato antigo
     * (lista simples de chaves, sem valor booleano) só pra não quebrar um
     * Contrato salvo antes do fix de 2026-09-24 -- ver
     * CreateContrato::mergeGroupedFeatures().
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $raw = $data['features'] ?? [];
        if (! is_array($raw)) {
            $raw = [];
        }

        $selected = array_is_list($raw)
            ? collect($raw)
            : collect($raw)->filter(fn ($v) => $v === true)->keys();

        foreach (ContratoResource::groupedFeatureOptions() as $groupName => $options) {
            $keys = array_keys($options);
            $data[ContratoResource::groupFieldName($groupName)] = $selected->intersect($keys)->values()->all();
        }

        return $data;
    }
}
