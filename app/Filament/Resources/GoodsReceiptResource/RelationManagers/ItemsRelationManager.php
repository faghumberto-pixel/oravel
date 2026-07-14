<?php

namespace App\Filament\Resources\GoodsReceiptResource\RelationManagers;

use App\Models\PurchaseOrderItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Nunca editada/excluida depois de criada (mesmo motivo documentado na
 * migration goods_receipt_items: manter o ledger de estoque confiavel) --
 * so' Create + View aqui, ao contrario do ItemsRelationManager de
 * PurchaseOrder.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Itens Recebidos';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('purchase_order_item_id')
                ->label('Item da Ordem de Compra')
                ->options(fn () => $this->getOwnerRecord()->purchaseOrder->items
                    ->filter(fn (PurchaseOrderItem $item) => $item->pending_quantity > 0)
                    ->mapWithKeys(fn (PurchaseOrderItem $item) => [
                        $item->id => "{$item->material?->name} (pendente: {$item->pending_quantity})",
                    ]))
                ->searchable()
                ->live()
                ->required(),
            Forms\Components\TextInput::make('quantity_received')
                ->label('Quantidade Recebida')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('serial_number')
                ->label('Número de Série')
                ->visible(fn (Get $get) => (bool) PurchaseOrderItem::find($get('purchase_order_item_id'))?->material?->requires_serial_number)
                ->required(fn (Get $get) => (bool) PurchaseOrderItem::find($get('purchase_order_item_id'))?->material?->requires_serial_number),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('purchaseOrderItem.material.sku')->label('SKU'),
                Tables\Columns\TextColumn::make('purchaseOrderItem.material.name')->label('Material'),
                Tables\Columns\TextColumn::make('quantity_received')->label('Qtd. Recebida')->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('serial_number')->label('Nº de Série')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('Registrado em')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
