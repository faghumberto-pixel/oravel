<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\GoodsReceiptResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('registrar_recebimento')
                ->label('Registrar Recebimento')
                ->color('success')
                ->icon('heroicon-o-inbox-arrow-down')
                ->url(fn () => GoodsReceiptResource::getUrl('create', ['purchase_order_id' => $this->record->id]))
                ->visible(fn () => in_array($this->record->status, [PurchaseOrder::STATUS_ABERTA, PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA], true)),
            Actions\Action::make('cancelar')
                ->label('Cancelar Ordem de Compra')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(fn () => ! in_array($this->record->status, [PurchaseOrder::STATUS_RECEBIDA, PurchaseOrder::STATUS_CANCELADA], true))
                ->action(function () {
                    $this->record->update(['status' => PurchaseOrder::STATUS_CANCELADA]);

                    Notification::make()->title('Ordem de Compra cancelada')->warning()->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
