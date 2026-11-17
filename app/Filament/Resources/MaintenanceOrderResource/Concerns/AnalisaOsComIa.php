<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Concerns;

use App\Models\AIAnalysis;
use App\Models\MaintenanceOrder;
use App\Services\MaintenanceOrderAnalysisService;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;

/** Botoes "Analisar a O.S. com IA" e "Ver última análise da IA" (so' consulta: a O.S. nunca e' alterada pela IA). */
trait AnalisaOsComIa
{
    /** @return array<int, Actions\Action> */
    protected function analiseIaActions(): array
    {
        $tem = fn (): bool => (bool) Tenancy::current()?->hasFeature('ia_diagnostico_avarias');

        return [
            Actions\Action::make('analisar_os_ia')
                ->label('Analisar a O.S. com IA')
                ->icon('heroicon-o-cpu-chip')
                ->visible(fn () => $tem() && $this->record->maintenance_type !== MaintenanceOrder::TYPE_RESERVA)
                ->requiresConfirmation()
                ->modalHeading('Analisar esta O.S. com IA?')
                ->modalDescription('A IA lê os dados da O.S. inteira (prazo, custo, peças, checklist, pendências e histórico do equipamento) e devolve uma avaliação com recomendações. É só um apoio: nada na O.S. é alterado.')
                ->modalSubmitActionLabel('Analisar')
                ->action(function (MaintenanceOrderAnalysisService $service): void {
                    $analysis = $service->analyze($this->record, (string) auth()->id());

                    if ($analysis->status === AIAnalysis::STATUS_CONCLUIDA) {
                        Notification::make()->title('Análise concluída')->success()
                            ->actions([NotificationAction::make('abrir')->label('Abrir e imprimir')->button()->url(route('ai-analyses.pdf', $analysis), shouldOpenInNewTab: true)])
                            ->send();
                    } else {
                        Notification::make()->title('Não foi possível concluir a análise')->body($analysis->error)->danger()->send();
                    }
                }),

            Actions\Action::make('ver_analise_os_ia')
                ->label('Ver última análise da IA')
                ->icon('heroicon-o-document-text')
                ->visible(fn () => $tem() && $this->ultimaAnaliseIa() !== null)
                ->url(fn () => ($a = $this->ultimaAnaliseIa()) ? route('ai-analyses.pdf', $a) : null)
                ->openUrlInNewTab(),
        ];
    }

    protected function ultimaAnaliseIa(): ?AIAnalysis
    {
        return AIAnalysis::query()
            ->where('type', AIAnalysis::TYPE_ORDEM_SERVICO)
            ->where('maintenance_order_id', $this->record->id)
            ->where('status', AIAnalysis::STATUS_CONCLUIDA)
            ->latest()
            ->first();
    }
}
