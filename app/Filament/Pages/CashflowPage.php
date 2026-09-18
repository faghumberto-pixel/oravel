<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CashflowAccumulatedChart;
use App\Filament\Widgets\FluxoDeCaixaProjetadoWidget;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

/**
 * Dashboard de fluxo de caixa consolidado: estatísticas por janela de
 * vencimento (30/60/90 dias) + gráfico de saldo acumulado dia a dia.
 *
 * Acesso restrito a admins ou usuários com permissão de leitura de
 * contas a receber ou contas a pagar.
 */
class CashflowPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.cashflow';

    protected static ?string $slug = 'fluxo-de-caixa';

    protected static ?string $title = 'Fluxo de Caixa';

    protected static ?string $navigationLabel = 'Fluxo de Caixa';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) Tenancy::current()
            && $user
            && ($user->isAdmin() || $user->can('ler_contas_receber') || $user->can('ler_contas_pagar'));
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderWidgets(): array
    {
        return [FluxoDeCaixaProjetadoWidget::class];
    }

    protected function getFooterWidgets(): array
    {
        return [CashflowAccumulatedChart::class];
    }
}
