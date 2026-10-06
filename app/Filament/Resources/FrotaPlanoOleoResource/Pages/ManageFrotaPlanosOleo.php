<?php

namespace App\Filament\Resources\FrotaPlanoOleoResource\Pages;

use App\Filament\Resources\FrotaPlanoOleoResource;
use App\Models\Asset;
use App\Services\Frota\OleoService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;

class ManageFrotaPlanosOleo extends ManageRecords
{
    protected static string $resource = FrotaPlanoOleoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Novo plano')->using(function (array $data) {
                try {
                    return app(OleoService::class)->salvarPlano(Asset::findOrFail($data['ativo_id']), $data);
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                    $this->halt();
                }
            }),
        ];
    }
}
