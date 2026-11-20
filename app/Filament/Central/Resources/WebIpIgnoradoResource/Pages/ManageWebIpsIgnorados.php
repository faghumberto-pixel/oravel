<?php

namespace App\Filament\Central\Resources\WebIpIgnoradoResource\Pages;

use App\Filament\Central\Resources\WebIpIgnoradoResource;
use App\Models\WebIpIgnorado;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Arr;

class ManageWebIpsIgnorados extends ManageRecords
{
    protected static string $resource = WebIpIgnoradoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Ignorar um IP')
                ->using(function (array $data): WebIpIgnorado {
                    $apagar = (bool) Arr::pull($data, 'apagar_historico', false);
                    $data['ip'] = trim($data['ip']);
                    $registro = WebIpIgnorado::create($data);

                    if ($apagar) {
                        $n = WebIpIgnorado::apagarVisitasDe($registro->ip);
                        Notification::make()->title($n === 0 ? 'Nenhuma visita anterior deste IP' : "{$n} visita(s) anterior(es) apagada(s)")->success()->send();
                    }

                    return $registro;
                }),
        ];
    }
}
