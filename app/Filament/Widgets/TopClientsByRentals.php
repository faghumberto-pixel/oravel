<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopClientsByRentals extends BaseWidget
{
    protected static ?string $heading = 'Top 5 Clientes com Mais Locações';

    protected int|string|array $columnSpan = ['md' => 2];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Client::withCount('contracts')
                    ->orderByDesc('contracts_count')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cliente'),
                Tables\Columns\TextColumn::make('contracts_count')
                    ->label('Contratos')
                    ->badge()
                    ->color('primary')
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Sem contratos registrados')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }
}
