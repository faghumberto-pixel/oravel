<?php

namespace App\Filament\Central\Resources\ProspectResource\RelationManagers;

use App\Models\AIAnalysis;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AIAnalysesRelationManager extends RelationManager
{
    protected static string $relationship = 'aiAnalyses';

    protected static ?string $title = 'Análises de IA';

    protected static ?string $recordTitleAttribute = 'type';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('type')->label('Tipo')->disabled(),
            Forms\Components\TextInput::make('status')->label('Status')->disabled(),
            Forms\Components\Textarea::make('response')->label('Resposta da IA')->disabled()->rows(10)->columnSpanFull(),
            Forms\Components\Textarea::make('error')->label('Erro')->disabled()->rows(3)->columnSpanFull(),
            Forms\Components\DateTimePicker::make('created_at')->label('Criado em')->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => AIAnalysis::STATUS_PENDENTE,
                        'success' => AIAnalysis::STATUS_CONCLUIDA,
                        'danger' => AIAnalysis::STATUS_FALHOU,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        AIAnalysis::STATUS_PENDENTE => 'Pendente',
                        AIAnalysis::STATUS_CONCLUIDA => 'Concluída',
                        AIAnalysis::STATUS_FALHOU => 'Falhou',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('type')->label('Tipo')->badge()->color('info')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        AIAnalysis::TYPE_PROSPECT => 'Prospect',
                        AIAnalysis::TYPE_COMERCIAL => 'Comercial',
                        AIAnalysis::TYPE_AVARIA => 'Avaria',
                        default => $state,
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([Tables\Actions\ViewAction::make()])
            ->emptyStateHeading('Nenhuma análise de IA realizada')
            ->emptyStateDescription('Clique em "Analisar com IA" no prospect para gerar uma análise.');
    }
}