<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CashflowAccumulatedChart;
use App\Filament\Widgets\FluxoDeCaixaProjetadoWidget;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Support\Tenancy;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;

class CashflowPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.cashflow';

    protected static ?string $slug = 'fluxo-de-caixa';

    protected static ?string $title = 'Fluxo de Caixa';

    protected static ?string $navigationLabel = 'Fluxo de Caixa';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 10;

    public ?string $dateStart = null;

    public ?string $dateEnd = null;

    public ?string $status = null;

    public ?string $type = null;

    public function mount(): void
    {
        $this->dateStart = now()->subDays(90)->format('Y-m-d');
        $this->dateEnd = now()->format('Y-m-d');
    }

    // Achado em simulação real 2026-09-24: sem modelo Eloquent próprio pra
    // rotear por Policy (agrega AccountPayable/AccountReceivable), então
    // checa o Contrato direto -- Fluxo de Caixa aparecia pra qualquer
    // usuário autenticado, plano incluído ou não.
    public static function canAccess(): bool
    {
        return (bool) Tenancy::current()?->hasFeature('tabela_cashflow_report');
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
        if (! $tenant) {
            return collect();
        }

        $dateStart = $this->dateStart ? Carbon::parse($this->dateStart) : now()->subDays(90);
        $dateEnd = $this->dateEnd ? Carbon::parse($this->dateEnd) : now();

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
        $ar = AccountReceivable::where('tenant_id', $tenant)
            ->whereBetween('due_date', [$dateStart, $dateEnd])
            ->when($this->status, fn ($q) => $q->whereIn('status', $statusFilter))
            ->when($this->type && $this->type !== 'AR', fn ($q) => $q->where(false))
            ->select('due_date as date', 'description', 'amount', 'status')
            ->addSelect(DB::raw("'AR' as type"), DB::raw('client_id'))
            ->with('client');

        // Contas a Pagar
        $ap = AccountPayable::where('tenant_id', $tenant)
            ->whereBetween('due_date', [$dateStart, $dateEnd])
            ->when($this->status, fn ($q) => $q->whereIn('status', $statusFilter))
            ->when($this->type && $this->type !== 'AP', fn ($q) => $q->where(false))
            ->select('due_date as date', 'description', 'amount', 'status')
            ->addSelect(DB::raw("'AP' as type"), DB::raw('null as client_id'));

        return $ar->union($ap)->orderBy('date', 'desc')->get();
    }
}
