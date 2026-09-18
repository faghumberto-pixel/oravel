<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CashflowAccumulatedChart;
use App\Filament\Widgets\FluxoDeCaixaProjetadoWidget;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Livewire\Attributes\Reactive;

class CashflowPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.pages.cashflow';
    protected static ?string $slug = 'fluxo-de-caixa';
    protected static ?string $title = 'Fluxo de Caixa';
    protected static ?string $navigationLabel = 'Fluxo de Caixa';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?int $navigationSort = 10;

    #[Reactive]
    public ?string $dateStart = null;

    #[Reactive]
    public ?string $dateEnd = null;

    #[Reactive]
    public ?string $status = null;

    #[Reactive]
    public ?string $type = null;

    public function mount(): void
    {
        $this->dateStart = now()->subDays(90)->format('Y-m-d');
        $this->dateEnd = now()->format('Y-m-d');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user();
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
