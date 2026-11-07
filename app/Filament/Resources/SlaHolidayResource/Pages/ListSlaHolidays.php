<?php

namespace App\Filament\Resources\SlaHolidayResource\Pages;

use App\Filament\Resources\SlaHolidayResource;
use App\Models\SlaHoliday;
use App\Services\Sla\SlaService;
use App\Support\FeriadosBr;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSlaHolidays extends ListRecords
{
    protected static string $resource = SlaHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importar')->label('Importar feriados nacionais')->icon('heroicon-o-arrow-down-on-square')->color('gray')
                ->modalHeading('Importar feriados nacionais')
                ->form([
                    Forms\Components\Select::make('ano')->label('Ano')->required()->default(now()->year)
                        ->options(collect(range(now()->year - 1, now()->year + 3))->mapWithKeys(fn ($a) => [$a => (string) $a])->all()),
                    Forms\Components\Toggle::make('facultativos')->label('Incluir pontos facultativos (Carnaval e Corpus Christi)')->default(false),
                    Forms\Components\Placeholder::make('aviso')->label('')->content('Feriados estaduais e municipais você cadastra à mão. Datas que já existem são mantidas.'),
                ])
                ->action(function (array $data): void {
                    $tenantId = (string) Tenancy::current()?->id;
                    $criados = 0;
                    $existentes = 0;

                    foreach (FeriadosBr::nacionais((int) $data['ano'], (bool) ($data['facultativos'] ?? false)) as $dia => $nome) {
                        $feriado = SlaHoliday::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenantId, 'date' => $dia], ['name' => $nome]);
                        $feriado->wasRecentlyCreated ? $criados++ : $existentes++;
                    }

                    SlaService::esquecer();
                    Notification::make()->title("{$criados} feriado(s) importado(s)")->body($existentes > 0 ? "{$existentes} já existiam e foram mantidos." : null)->success()->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
