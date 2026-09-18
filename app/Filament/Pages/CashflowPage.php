<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CashflowAccumulatedChart;
use App\Filament\Widgets\FluxoDeCaixaProjetadoWidget;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Client;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashflowPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.pages.cashflow';
    protected static ?string $slug = 'fluxo-de-caixa';
    protected static ?string $title = 'Fluxo de Caixa';
    protected static ?string $navigationLabel = 'Fluxo de Caixa';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?int $navigationSort = 10;

    public ?string $dateStart = null;
    public ?string $dateEnd = null;
    public ?string $clientId = null;
    public ?string $status = null;
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

    public function table(Table $table): Table
    {
        $query = $this->getDetailQuery();

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('date')->label('Data')->date('d/m/Y')->sortable(),
                TextColumn::make('client_name')->label('Cliente'),
                TextColumn::make('description')->label('Descrição'),
                TextColumn::make('type')->label('Tipo')->badge(),
                TextColumn::make('amount')->label('Valor')->money('BRL')->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->paginated([25, 50, 100])
            ->defaultSort('date', 'desc');
    }

    private function getDetailQuery(): Builder
    {
        $tenant = auth()->user()?->tenant_id;

        return collect([
            AccountReceivable::where('tenant_id', $tenant)
                ->select('due_date as date', 'client_id', 'description', 'amount', 'status')
                ->addSelect(\Illuminate\Support\Facades\DB::raw("'AR' as type")),
            AccountPayable::where('tenant_id', $tenant)
                ->select('due_date as date', \Illuminate\Support\Facades\DB::raw("null as client_id"), 'description', 'amount', 'status')
                ->addSelect(\Illuminate\Support\Facades\DB::raw("'AP' as type")),
        ])->first();
    }
}
