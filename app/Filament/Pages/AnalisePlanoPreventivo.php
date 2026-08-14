<?php

namespace App\Filament\Pages;

use App\Models\AIAnalysis;
use App\Services\PreventiveMaintenanceAnalysisService;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Analise de planos preventivos via IA -- equipamentos com horas acima do
 * plano, equipamentos que quebram logo apos a preventiva, e tempo que
 * ficaram trabalhando sem quebrar (MTBF simplificado), calculados de
 * verdade por App\Services\PreventiveMaintenanceAnalysisService; a IA so'
 * interpreta/prioriza por cima dos numeros.
 *
 * Navegacao manual dentro do submenu "PMP" (AdminPanelProvider), nao
 * auto-registrada -- e' especifico de preventiva, mesmo padrao de
 * MaintenancePlanResource/PreventiveMaintenanceExecutionResource/PainelPmp.
 */
class AnalisePlanoPreventivo extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationLabel = 'Análise de Planos Preventivos (IA)';

    protected static ?string $title = 'Análise de Planos Preventivos';

    protected static string $view = 'filament.pages.analise-plano-preventivo';

    public ?AIAnalysis $analysis = null;

    public int $thresholdQuebraDias = 30;

    public static function canAccess(): bool
    {
        return (bool) Tenancy::current()?->hasFeature('ia_diagnostico_avarias');
    }

    public function getThresholdOptions(): array
    {
        return [15 => '15 dias', 30 => '30 dias', 60 => '60 dias', 90 => '90 dias'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('analisar')
                ->label('Analisar planos preventivos com IA')
                ->icon('heroicon-o-cpu-chip')
                ->requiresConfirmation()
                ->modalHeading('Analisar planos preventivos com IA?')
                ->modalDescription('Verifica equipamentos com preventiva vencida, quebras logo após a preventiva, e tempo médio trabalhando sem quebrar.')
                ->modalSubmitActionLabel('Analisar')
                ->action(function (PreventiveMaintenanceAnalysisService $service): void {
                    $this->analysis = $service->analyzeTenant(Tenancy::current()->id, auth()->id(), $this->thresholdQuebraDias);

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
