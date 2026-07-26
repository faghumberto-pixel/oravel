<?php

namespace App\Filament\Pages;

use App\Models\AIAnalysis;
use App\Services\CorrectiveReworkAnalysisService;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Analise de retrabalho/corretivas via IA -- quantas vezes voltamos ao
 * mesmo equipamento, em quanto tempo, e as principais causas, calculados de
 * verdade por App\Services\CorrectiveReworkAnalysisService; a IA so'
 * interpreta/prioriza por cima dos numeros.
 *
 * Item solto dentro de PCM (nao entra no submenu "PMP") -- retrabalho e'
 * sobre corretivas, nao e' especifico de planejamento preventivo.
 */
class AnaliseRetrabalho extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'PCM';

    protected static ?string $navigationLabel = 'Análise de Retrabalho (IA)';

    protected static ?string $title = 'Análise de Retrabalho / Corretivas';

    protected static string $view = 'filament.pages.analise-retrabalho';

    public ?AIAnalysis $analysis = null;

    public int $janelaDias = 30;

    public static function canAccess(): bool
    {
        return (bool) Tenancy::current()?->hasFeature('ia_diagnostico_avarias');
    }

    public function getJanelaOptions(): array
    {
        return [15 => '15 dias', 30 => '30 dias', 60 => '60 dias', 90 => '90 dias'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('analisar')
                ->label('Analisar retrabalho com IA')
                ->icon('heroicon-o-cpu-chip')
                ->requiresConfirmation()
                ->modalHeading('Analisar retrabalho com IA?')
                ->modalDescription('Verifica ativos que receberam mais de uma OS corretiva dentro da janela escolhida e pede uma interpretação das causas por IA.')
                ->modalSubmitActionLabel('Analisar')
                ->action(function (CorrectiveReworkAnalysisService $service): void {
                    $this->analysis = $service->analyzeTenant(Tenancy::current()->id, auth()->id(), $this->janelaDias);

                    if ($this->analysis->status === AIAnalysis::STATUS_CONCLUIDA) {
                        Notification::make()->title('Análise concluída')->success()->send();
                    } else {
                        Notification::make()
                            ->title('Não foi possível concluir a análise')
                            ->body($this->analysis->error)
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
