<?php

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['features']) && ! is_array($data['features'])) {
            $data['features'] = [];
        }

        return $data;
    }

    /**
     * Mesma correcao aplicada em PlanResource\Pages\EditPlan -- tenants com
     * override aditivo gravado no formato antigo (dicionario {chave: true})
     * fariam o CheckboxList marcar todas as opcoes e o toggle de uma afetar
     * todas (in_array() com comparacao frouxa contra puros `true`).
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
