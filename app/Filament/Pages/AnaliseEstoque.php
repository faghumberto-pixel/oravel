<?php

namespace App\Filament\Pages;

use App\Models\AIAnalysis;
use App\Services\StockAnalysisService;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Analise de saude do estoque via IA -- materiais criticos (abaixo do
 * minimo, com estimativa de dias ate esgotar) e materiais parados (saldo
 * sem giro), calculados de verdade por App\Services\StockAnalysisService;
 * a IA so' escreve o resumo/priorizacao por cima dos numeros.
 */
class AnaliseEstoque extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Análise de Estoque (IA)';

    protected static ?string $title = 'Análise de Estoque';

    protected static string $view = 'filament.pages.analise-estoque';

    public ?AIAnalysis $analysis = null;

    public static function canAccess(): bool
    {
        // Mesmo flag guarda-chuva do resto do AI Assistant (Avarias/
        // Logistica/Comercial) -- ver nota identica em OtimizacaoRotas.
        return (bool) Tenancy::current()?->hasFeature('ia_diagnostico_avarias');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('analisar')
                ->label('Analisar estoque com IA')
                ->icon('heroicon-o-cpu-chip')
                ->requiresConfirmation()
                ->modalHeading('Analisar estoque com IA?')
                ->modalDescription('Verifica todos os materiais abaixo do estoque mínimo e parados sem giro, e pede uma priorização de compra por IA.')
                ->modalSubmitActionLabel('Analisar')
                ->action(function (StockAnalysisService $service): void {
                    $this->analysis = $service->analyzeTenant(Tenancy::current()->id, auth()->id());

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
