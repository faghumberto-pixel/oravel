<?php

namespace App\Filament\Pages;

use App\Domain\Intelligence\Concerns\UsaResumoDoIntelligence;
use App\Domain\Intelligence\Support\Graficos;
use App\Http\Controllers\IntelligenceRelatorioController;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

/** Intelligence › Índice de Saúde: o Índice Oravel de Saúde da Locadora e como ele evoluiu. Chave de contrato: menu_intelligence_dashboard. */
class IntelligenceDashboard extends Page
{
    use UsaResumoDoIntelligence;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = 'Intelligence';

    protected static ?string $navigationLabel = 'Índice de Saúde';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Índice Oravel de Saúde da Locadora';

    protected static ?string $slug = 'intelligence-dashboard';

    protected static string $view = 'filament.pages.intelligence-dashboard';

    /** Rotas extras DENTRO do painel (mesmos middlewares: login, 2FA, bloqueio por inadimplência) para os botões de relatório de todas as telas. */
    public static function routes(Panel $panel): void
    {
        parent::routes($panel);

        Route::get(static::getRoutePath().'/relatorio/{acao}', IntelligenceRelatorioController::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->name(static::getRelativeRouteName().'.relatorio');
    }

    protected function getHeaderActions(): array
    {
        return $this->acoesDeRelatorio('painel');
    }

    /** @return array<string, mixed> */
    public function graficos(array $resumo): array
    {
        $g = app(Graficos::class);

        return $g->todos($resumo) + ['kpis' => $g->kpis($resumo), 'calor' => $g->calor($resumo)];
    }
}
