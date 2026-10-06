<?php

namespace App\Filament\Resources\VehicleExpirationResource\Pages;

use App\Filament\Resources\VehicleExpirationResource;
use App\Support\VehicleExpirations;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListVehicleExpirations extends ListRecords
{
    protected static string $resource = VehicleExpirationResource::class;

    protected static ?string $title = 'Vencimento de veículos';

    /** Documento escolhido no filtro (null = qualquer): as abas valem para ele. */
    private function selectedDocument(): ?string
    {
        return $this->tableFilters['documento']['value'] ?? null;
    }

    public function getTabs(): array
    {
        $tabs = ['todos' => Tab::make('Todos')];

        foreach (VehicleExpirations::situations() as $key => $label) {
            $tabs[$key] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => VehicleExpirations::apply($query, $this->selectedDocument(), $key))
                ->badge(fn () => VehicleExpirationResource::getEloquentQuery()->tap(fn (Builder $q) => VehicleExpirations::apply($q, $this->selectedDocument(), $key))->count())
                ->badgeColor(match ($key) {
                    'vencido' => 'danger',
                    'ate_30' => 'warning',
                    default => 'gray',
                });
        }

        return $tabs;
    }
}
