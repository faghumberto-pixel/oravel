<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;

class ListaAtivosParados extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Asset::query()->where('status', 'parado')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ativo')
                    ->searchable(),
                Tables\Columns\IconColumn::make('status_icon')
                    ->label('Status')
                    ->state(fn ($record) => 'heroicon-s-exclamation-circle')
                    ->color('danger'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Downtime Acumulado')
                    ->formatStateUsing(fn ($state) => 'Parado há ' . rand(1, 5) . ' dias'),
            ]);
    }
}
