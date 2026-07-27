<?php

namespace App\Filament\Resources\CrmLeadResource\Pages;

use App\Filament\Pages\CaixaDeEmail;
use App\Filament\Resources\AIAnalysisResource;
use App\Filament\Resources\CrmLeadResource;
use App\Models\AIAnalysis;
use App\Models\CrmLead;
use App\Services\CommercialLeadAnalysisService;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCrmLead extends EditRecord
{
    protected static string $resource = CrmLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('enviar_email')
                ->label('Enviar E-mail')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->visible(fn (CrmLead $record) => filled($record->email))
                ->url(fn (CrmLead $record) => CaixaDeEmail::getUrl([
                    'to' => $record->email,
                    'related_type' => CrmLead::class,
                    'related_id' => $record->id,
                ])),

            Actions\Action::make('analisar_ia')
                ->label('Analisar risco com IA')
                ->color('gray')
                ->icon('heroicon-o-cpu-chip')
                ->visible(fn () => (bool) Tenancy::current()?->hasFeature('ia_diagnostico_avarias')
                    && $this->record->isOpen())
                ->requiresConfirmation()
                ->modalHeading('Analisar risco de perda com IA?')
                ->modalDescription('Envia os dados deste lead (funil, interações, taxa de conversão da empresa) para análise por IA. É só uma sugestão de apoio — nada muda automaticamente no lead.')
                ->modalSubmitActionLabel('Analisar')
                ->action(function (CommercialLeadAnalysisService $service): void {
                    $analysis = $service->analyze($this->record, auth()->id());

                    if ($analysis->status === AIAnalysis::STATUS_CONCLUIDA) {
                        Notification::make()
                            ->title('Análise concluída')
                            ->success()
                            ->actions([
                                NotificationAction::make('ver')
                                    ->label('Ver análise')
                                    ->url(AIAnalysisResource::getUrl('view', ['record' => $analysis]))
                                    ->button(),
                            ])
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Não foi possível concluir a análise')
                            ->body($analysis->error)
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
