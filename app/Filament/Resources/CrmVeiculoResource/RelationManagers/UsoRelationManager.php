<?php

namespace App\Filament\Resources\CrmVeiculoResource\RelationManagers;

use App\Models\CrmVisita;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/** Histórico de uso do veículo: visitas e km rodados (somente leitura). */
class UsoRelationManager extends RelationManager
{
    protected static string $relationship = 'visitas';

    protected static ?string $title = 'Histórico de uso';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('agendada_para', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('agendada_para')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('vendedor.name')->label('Vendedor')->placeholder('—'),
                Tables\Columns\TextColumn::make('alvo')->label('Visita')->state(fn (CrmVisita $r) => $r->alvoNome()),
                Tables\Columns\TextColumn::make('km_inicio')->label('Km inicial')->placeholder('—'),
                Tables\Columns\TextColumn::make('km_fim')->label('Km final')->placeholder('—'),
                Tables\Columns\TextColumn::make('rodados')->label('Km rodados')->state(fn (CrmVisita $r) => $r->kmRodados())
                    ->placeholder('—')
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()->label('Total')->using(fn ($query) => (int) $query->sum(DB::raw('GREATEST(coalesce(km_fim,0) - coalesce(km_inicio,0), 0)')))),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => CrmVisita::statusLabels()[$state] ?? $state),
            ]);
    }
}
