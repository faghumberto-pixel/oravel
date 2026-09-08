<?php

namespace App\Filament\Pages;

use App\Models\Part;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Transferência em lote do Almoxarifado central pro Almoxarifado Volante
 * (veículo/técnico) -- interface administrativa pra
 * StockTransferService::transferToMobile(), que existia sem nenhuma tela
 * chamando ela ainda.
 */
class TransferenciaEstoque extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationParentItem = 'Gestão de Estoque';

    protected static ?string $navigationLabel = 'Transferência para Volante';

    protected static ?string $title = 'Transferência para Almoxarifado Volante';

    protected static string $view = 'filament.pages.transferencia-estoque';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        $tenantId = Tenancy::current()?->id;

        return $form
            ->schema([
                Forms\Components\Section::make('Origem e Destino')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('source_warehouse_id')
                            ->label('Almoxarifado de Origem')
                            ->options(fn () => Warehouse::where('tenant_id', $tenantId)
                                ->where('type', '!=', Warehouse::TYPE_MOBILE)
                                ->active()
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('destination_warehouse_id')
                            ->label('Veículo / Técnico de Destino')
                            ->options(fn () => Warehouse::where('tenant_id', $tenantId)
                                ->where('type', Warehouse::TYPE_MOBILE)
                                ->active()
                                ->get()
                                ->mapWithKeys(fn (Warehouse $w) => [
                                    $w->id => $w->name.($w->vehicle_plate ? " ({$w->vehicle_plate})" : ''),
                                ]))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Só aparecem almoxarifados do tipo Volante. Cadastre um em Almoxarifados.'),
                    ]),

                Forms\Components\Section::make('Peças a Transferir')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('part_id')
                                    ->label('Peça')
                                    ->options(fn () => Part::where('tenant_id', $tenantId)
                                        ->where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(fn (Part $p) => [$p->id => "{$p->sku} — {$p->name}"]))
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantidade')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->required(),
                            ])
                            ->columns(3)
                            ->addActionLabel('Adicionar peça')
                            ->required()
                            ->minItems(1),
                    ]),
            ])
            ->statePath('data');
    }

    public function transferir(): void
    {
        $state = $this->form->getState();

        try {
            $movements = app(StockTransferService::class)->transferToMobile(
                (int) $state['source_warehouse_id'],
                (int) $state['destination_warehouse_id'],
                $state['items'],
                Auth::id()
            );
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Não foi possível transferir')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $itemCount = count($state['items']);

        $this->form->fill();

        Notification::make()
            ->title('Transferência concluída')
            ->body("{$itemCount} peça(s) transferida(s) — ".count($movements).' movimentações registradas.')
            ->success()
            ->send();
    }
}
