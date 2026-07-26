<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoodsReceiptResource\Pages;
use App\Filament\Resources\GoodsReceiptResource\RelationManagers\ItemsRelationManager;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recebimento fisico de uma PurchaseOrder -- pode ser parcial (varios
 * GoodsReceipt numa mesma Ordem de Compra). Efeito colateral (baixa de
 * estoque, ledger, quantity_received, status da Ordem de Compra) vive
 * em App\Observers\GoodsReceiptItemObserver, disparado ao adicionar um
 * item aqui (ItemsRelationManager).
 */
class GoodsReceiptResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = GoodsReceipt::class;

    protected static ?string $modelLabel = 'Recebimento';

    protected static ?string $pluralModelLabel = 'Recebimentos de Compra';

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Recebimento')
                ->schema([
                    Forms\Components\Select::make('purchase_order_id')
                        ->label('Ordem de Compra')
                        ->relationship(
                            'purchaseOrder',
                            'id',
                            fn (Builder $query) => $query
                                ->where('tenant_id', Tenancy::current()?->id)
                                ->whereIn('status', [PurchaseOrder::STATUS_ABERTA, PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA])
                        )
                        ->getOptionLabelFromRecordUsing(fn (PurchaseOrder $record) => "#{$record->id} — {$record->supplier?->name}")
                        ->default(fn () => request()->query('purchase_order_id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(fn (?GoodsReceipt $record) => $record !== null),
                    Forms\Components\Select::make('internal_unit_id')
                        ->label('Filial de Destino')
                        ->relationship('internalUnit', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('received_by_user_id')
                        ->label('Recebido por')
                        ->relationship('receivedBy', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->default(fn () => auth()->id())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\DateTimePicker::make('received_at')
                        ->label('Data/Hora do Recebimento')
                        ->default(now())
                        ->required(),
                    Forms\Components\TextInput::make('invoice_number')
                        ->label('Número da Nota Fiscal')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Recebido em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('purchaseOrder.supplier.name')
                    ->label('Fornecedor'),
                Tables\Columns\TextColumn::make('internalUnit.name')
                    ->label('Filial'),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Nota Fiscal')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('receivedBy.name')
                    ->label('Recebido por'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Itens')
                    ->counts('items'),
            ])
            ->defaultSort('received_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoodsReceipts::route('/'),
            'create' => Pages\CreateGoodsReceipt::route('/create'),
            'edit' => Pages\EditGoodsReceipt::route('/{record}/edit'),
        ];
    }
}
