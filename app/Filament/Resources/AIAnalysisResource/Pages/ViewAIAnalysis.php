<?php

namespace App\Filament\Resources\AIAnalysisResource\Pages;

use App\Filament\Resources\AIAnalysisResource;
use App\Filament\Resources\CrmLeadResource;
use App\Models\AIAnalysis;
use Filament\Actions;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAIAnalysis extends ViewRecord
{
    protected static string $resource = AIAnalysisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('imprimir')
                ->label('Imprimir (PDF)')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn () => $this->record->status === AIAnalysis::STATUS_CONCLUIDA)
                ->url(fn () => route('ai-analyses.pdf', $this->record))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Components\Section::make('Identificação')
                ->schema([
                    Components\TextEntry::make('type')
                        ->label('Tipo')
                        ->formatStateUsing(fn (string $state) => AIAnalysisResource::typeLabels()[$state] ?? $state),
                    Components\TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => AIAnalysisResource::statusLabels()[$state] ?? $state)
                        ->color(fn (string $state) => match ($state) {
                            AIAnalysis::STATUS_CONCLUIDA => 'success',
                            AIAnalysis::STATUS_FALHOU => 'danger',
                            default => 'gray',
                        }),
                    Components\TextEntry::make('user.name')->label('Solicitado por'),
                    Components\TextEntry::make('created_at')->label('Data')->dateTime('d/m/Y H:i'),
                    Components\TextEntry::make('reference_label')
                        ->label('Referência')
                        ->placeholder('—')
                        ->url(fn (AIAnalysis $record) => $record->type === AIAnalysis::TYPE_COMERCIAL && $record->crm_lead_id
                            ? CrmLeadResource::getUrl('edit', ['record' => $record->crm_lead_id])
                            : null),
                ])
                ->columns(4),

            Components\TextEntry::make('error')
                ->label('Erro')
                ->columnSpanFull()
                ->color('danger')
                ->visible(fn ($record) => $record->status === AIAnalysis::STATUS_FALHOU && $record->error),

            Components\ViewEntry::make('response')
                ->hiddenLabel()
                ->view('filament.infolists.ai-analysis-result')
                ->visible(fn ($record) => $record->status === AIAnalysis::STATUS_CONCLUIDA),
        ]);
    }
}
