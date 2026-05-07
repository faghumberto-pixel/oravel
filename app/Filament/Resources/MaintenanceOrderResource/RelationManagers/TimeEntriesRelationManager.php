<?php

namespace App\Filament\Resources\MaintenanceOrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TimeEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'timeEntries';
    protected static ?string $title = 'Apontamentos de Mão de Obra';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('description')
                ->label('O que foi feito?')
                ->required()
                ->columnSpanFull(),
            
            // Campos invisíveis para o técnico, preenchidos pelo sistema
            Forms\Components\DateTimePicker::make('started_at')
                ->label('Início')
                ->disabled(),
            Forms\Components\DateTimePicker::make('stopped_at')
                ->label('Fim')
                ->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Início')
                    ->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('stopped_at')
                    ->label('Fim')
                    ->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('total_hours')
                    ->label('Tempo Total')
                    ->state(fn ($record) => $this->calculateDuration($record)),
                Tables\Columns\TextColumn::make('user.name')->label('Técnico'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Iniciar Novo Serviço')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['started_at'] = now();
                        $data['user_id'] = Auth::id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('stop')
                    ->label('Finalizar Serviço')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->action(fn ($record) => $record->update(['stopped_at' => now()]))
                    ->visible(fn ($record) => is_null($record->stopped_at)),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    protected function calculateDuration($record)
    {
        if (!$record->stopped_at) return 'Em andamento...';
        return Carbon::parse($record->started_at)->diffInMinutes($record->stopped_at) . ' min';
    }
}