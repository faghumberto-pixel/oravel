<?php

namespace App\Filament\Resources\FrotaSinistroResource\Pages;

use App\Filament\Resources\FrotaSinistroResource;
use App\Models\Asset;
use App\Services\Frota\SinistroService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateFrotaSinistro extends CreateRecord
{
    protected static string $resource = FrotaSinistroResource::class;

    protected static bool $canCreateAnother = false;

    /** Passa pelo serviço (regras e condutor sugerido); as fotos são salvas pelo Filament em seguida. */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SinistroService::class)->registrar(Asset::findOrFail($data['ativo_id']), $data, auth()->user());
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
            $this->halt();
        }
    }
}
