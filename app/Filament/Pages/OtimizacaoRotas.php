<?php

namespace App\Filament\Pages;

use App\Models\AIAnalysis;
use App\Services\LogisticsRouteAnalysisService;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Analise de rotas do dia (por veiculo) via IA -- distancia calculada de
 * verdade (Haversine, ver App\Services\RouteOptimizationService), a IA so'
 * escreve o resumo por cima. Pagina separada da Programacao (calendario)
 * porque o formato de exibicao (rotas por veiculo, nao eventos por data)
 * e' bem diferente.
 */
class OtimizacaoRotas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationLabel = 'Otimização de Rotas (IA)';

    protected static ?string $title = 'Otimização de Rotas';

    protected static string $view = 'filament.pages.otimizacao-rotas';

    public string $date;

    public ?AIAnalysis $analysis = null;

    public static function canAccess(): bool
    {
        // 'ia_diagnostico_avarias' e' hoje um flag guarda-chuva pro AI
        // Assistant inteiro (Avarias + Logistica), nao so' avarias --
        // nome ficou do primeiro caso de uso construido. Se algum dia
        // precisar liberar so' um dos dois por plano, criar um feature
        // key proprio pra logistica nesse ponto.
        return (bool) Tenancy::current()?->hasFeature('ia_diagnostico_avarias');
    }

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('analisar')
                ->label('Analisar rotas com IA')
                ->icon('heroicon-o-cpu-chip')
                ->form([
                    Forms\Components\DatePicker::make('date')
                        ->label('Data')
                        ->default(fn () => $this->date)
                        ->required(),
                ])
                ->action(function (array $data, LogisticsRouteAnalysisService $service): void {
                    $this->date = $data['date'];

                    $this->analysis = $service->analyzeDate(
                        Tenancy::current()->id,
                        auth()->id(),
                        $this->date,
                    );

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
