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

    public function getDetailRecords()
    {
        $tenant = auth()->user()?->tenant_id;
        if (!$tenant) {
            return collect();
        }

        $dateStart = $this->dateStart ? \Carbon\Carbon::parse($this->dateStart) : now()->subDays(90);
        $dateEnd = $this->dateEnd ? \Carbon\Carbon::parse($this->dateEnd) : now();

        $statusFilter = [];
        if ($this->status === 'pendente') {
            $statusFilter = ['pendente'];
        } elseif ($this->status === 'atrasado') {
            $statusFilter = ['atrasado'];
        } elseif ($this->status === 'pago') {
            $statusFilter = ['pago'];
        } else {
            $statusFilter = ['pendente', 'atrasado', 'pago'];
        }

        // Contas a Receber
        $ar = \App\Models\AccountReceivable::where('tenant_id', $tenant)
            ->whereBetween('due_date', [$dateStart, $dateEnd])
            ->when($this->status, fn ($q) => $q->whereIn('status', $statusFilter))
            ->when($this->type && $this->type !== 'AR', fn ($q) => $q->where(false))
            ->select('due_date as date', 'description', 'amount', 'status')
            ->addSelect(\Illuminate\Support\Facades\DB::raw("'AR' as type"), \Illuminate\Support\Facades\DB::raw("client_id"))
            ->with('client');

        // Contas a Pagar
        $ap = \App\Models\AccountPayable::where('tenant_id', $tenant)
            ->whereBetween('due_date', [$dateStart, $dateEnd])
            ->when($this->status, fn ($q) => $q->whereIn('status', $statusFilter))
            ->when($this->type && $this->type !== 'AP', fn ($q) => $q->where(false))
            ->select('due_date as date', 'description', 'amount', 'status')
            ->addSelect(\Illuminate\Support\Facades\DB::raw("'AP' as type"), \Illuminate\Support\Facades\DB::raw("null as client_id"));

        return $ar->union($ap)->orderBy('date', 'desc')->get();
    }
}
