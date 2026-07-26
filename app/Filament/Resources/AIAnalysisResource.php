<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AIAnalysisResource\Pages;
use App\Models\AIAnalysis;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Central de IA": historico de todas as analises geradas (avaria/
 * logistica/comercial/estoque), consultavel e imprimivel (PDF, ver
 * App\Http\Controllers\AIAnalysisPdfController). So' leitura -- as
 * analises sao criadas pelos servicos (EquipmentDamageDiagnosisService
 * etc), nunca por um formulario aqui.
 */
class AIAnalysisResource extends BaseResource
{
    protected static ?string $model = AIAnalysis::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'PCM';

    protected static ?string $navigationLabel = 'Central de IA';

    protected static ?string $modelLabel = 'Análise de IA';

    protected static ?string $pluralModelLabel = 'Central de IA';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['equipmentDamage.asset', 'crmLead', 'user']);
    }

    public static function typeLabels(): array
    {
        return [
            AIAnalysis::TYPE_AVARIA => 'Diagnóstico de Avaria',
            AIAnalysis::TYPE_COMERCIAL => 'Análise Comercial',
            AIAnalysis::TYPE_ESTOQUE => 'Análise de Estoque',
            AIAnalysis::TYPE_LOGISTICA => 'Otimização de Rotas',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            AIAnalysis::STATUS_PENDENTE => 'Processando',
            AIAnalysis::STATUS_CONCLUIDA => 'Concluída',
            AIAnalysis::STATUS_FALHOU => 'Falhou',
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::typeLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        AIAnalysis::TYPE_AVARIA => 'danger',
                        AIAnalysis::TYPE_LOGISTICA => 'info',
                        AIAnalysis::TYPE_COMERCIAL => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        AIAnalysis::STATUS_CONCLUIDA => 'success',
                        AIAnalysis::STATUS_FALHOU => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reference_label')
                    ->label('Referência')
                    ->placeholder('—')
                    ->url(fn (AIAnalysis $record) => $record->type === AIAnalysis::TYPE_COMERCIAL && $record->crm_lead_id
                        ? CrmLeadResource::getUrl('edit', ['record' => $record->crm_lead_id])
                        : null),
                Tables\Columns\TextColumn::make('user.name')->label('Solicitado por'),
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('Tipo')->options(self::typeLabels()),
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(self::statusLabels()),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('imprimir')
                    ->label('Imprimir (PDF)')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->visible(fn (AIAnalysis $record) => $record->status === AIAnalysis::STATUS_CONCLUIDA)
                    ->url(fn (AIAnalysis $record) => route('ai-analyses.pdf', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAIAnalyses::route('/'),
            'view' => Pages\ViewAIAnalysis::route('/{record}'),
        ];
    }
}
