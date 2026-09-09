<?php

namespace App\Filament\Resources\GoodsReceiptResource\Pages;

use App\Filament\Resources\GoodsReceiptResource;
use App\Models\SupplierEvaluation;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditGoodsReceipt extends EditRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('gerar_conta_pagar')
                ->label('Gerar Conta a Pagar')
                ->color('success')
                ->icon('heroicon-o-banknotes')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->items()->exists() && ! $this->record->accountPayable()->exists())
                ->action(function () {
                    $this->record->generateAccountPayable();

                    Notification::make()->title('Conta a Pagar gerada')->success()->send();
                }),

            Actions\Action::make('avaliar_fornecedor')
                ->label('Avaliar Fornecedor')
                ->color('info')
                ->icon('heroicon-o-star')
                ->form([
                    Forms\Components\Select::make('score_prazo_entrega')
                        ->label('Prazo de Entrega')
                        ->options([1 => '1 - Ruim', 2 => '2 - Regular', 3 => '3 - Bom', 4 => '4 - Ótimo', 5 => '5 - Excelente'])
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('score_qualidade')
                        ->label('Qualidade')
                        ->options([1 => '1 - Ruim', 2 => '2 - Regular', 3 => '3 - Bom', 4 => '4 - Ótimo', 5 => '5 - Excelente'])
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('score_preco')
                        ->label('Preço')
                        ->options([1 => '1 - Ruim', 2 => '2 - Regular', 3 => '3 - Bom', 4 => '4 - Ótimo', 5 => '5 - Excelente'])
                        ->required()
                        ->native(false),
                    Forms\Components\Textarea::make('notes')
                        ->label('Observações'),
                ])
                ->visible(fn () => $this->record->purchaseOrder?->supplier_id !== null)
                ->action(function (array $data) {
                    SupplierEvaluation::create([
                        'tenant_id' => $this->record->tenant_id,
                        'supplier_id' => $this->record->purchaseOrder->supplier_id,
                        'purchase_order_id' => $this->record->purchase_order_id,
                        'evaluated_by_user_id' => auth()->id(),
                        'evaluated_at' => now(),
                        ...$data,
                    ]);

                    Notification::make()->title('Avaliação registrada')->success()->send();
                }),
        ];
    }
}
