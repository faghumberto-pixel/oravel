<?php

namespace App\Filament\Resources\MaterialRequestResource\Pages;

use App\Filament\Resources\MaterialRequestResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\MaterialRequest;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditMaterialRequest extends EditRecord
{
    protected static string $resource = MaterialRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('enviar_aprovacao')
                ->label('Enviar para Aprovação')
                ->color('info')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $this->record->status === MaterialRequest::STATUS_RASCUNHO
                    && $this->record->items()->exists())
                ->action(function () {
                    $this->record->update(['status' => MaterialRequest::STATUS_AGUARDANDO_APROVACAO]);
                    $this->notifyAdmins('Requisição de compra aguardando aprovação');

                    Notification::make()->title('Enviada para aprovação')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('aprovar')
                ->label('Aprovar')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === MaterialRequest::STATUS_AGUARDANDO_APROVACAO
                    && auth()->user()?->can('update', $this->record))
                ->action(function () {
                    $this->record->update([
                        'status' => MaterialRequest::STATUS_APROVADA,
                        'approved_by_user_id' => auth()->id(),
                        'approved_at' => now(),
                    ]);

                    Notification::make()->title('Requisição aprovada')->success()->send();
                    $this->refreshFormData(['status', 'approved_by_user_id', 'approved_at']);
                }),

            Actions\Action::make('recusar')
                ->label('Recusar')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === MaterialRequest::STATUS_AGUARDANDO_APROVACAO
                    && auth()->user()?->can('update', $this->record))
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo da recusa')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => MaterialRequest::STATUS_RECUSADA,
                        'approved_by_user_id' => auth()->id(),
                        'approved_at' => now(),
                        'rejection_reason' => $data['reason'],
                    ]);

                    Notification::make()->title('Requisição recusada')->warning()->send();
                    $this->refreshFormData(['status', 'rejection_reason']);
                }),

            // So' habilita depois de aprovada + ter uma cotacao marcada
            // como selecionada (QuotationsRelationManager) -- copia preco
            // por item do proprio MaterialRequestItem.cost_price (a
            // cotacao guarda so' o total agregado, nao por item), nao da
            // cotacao. So' gera uma vez por requisicao.
            Actions\Action::make('gerar_ordem_compra')
                ->label('Gerar Ordem de Compra')
                ->color('success')
                ->icon('heroicon-o-shopping-cart')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === MaterialRequest::STATUS_APROVADA
                    && $this->record->quotations()->where('is_selected', true)->exists()
                    && ! $this->record->purchaseOrders()->exists())
                ->action(function () {
                    $quotation = $this->record->quotations()->where('is_selected', true)->first();

                    $purchaseOrder = DB::transaction(function () use ($quotation) {
                        $purchaseOrder = PurchaseOrder::create([
                            'tenant_id' => $this->record->tenant_id,
                            'material_request_id' => $this->record->id,
                            'material_request_quotation_id' => $quotation->id,
                            'supplier_id' => $quotation->supplier_id,
                            'status' => PurchaseOrder::STATUS_ABERTA,
                            'expected_delivery_date' => $quotation->delivery_days ? now()->addDays($quotation->delivery_days) : null,
                            'created_by_user_id' => auth()->id(),
                        ]);

                        $total = 0;

                        foreach ($this->record->items as $item) {
                            $unitPrice = (float) ($item->cost_price ?? $item->material?->last_purchase_price ?? $item->material?->unit_cost ?? 0);

                            $purchaseOrder->items()->create([
                                'tenant_id' => $this->record->tenant_id,
                                'material_id' => $item->material_id,
                                'quantity' => $item->quantity,
                                'unit_price' => $unitPrice,
                            ]);

                            $total += $unitPrice * (float) $item->quantity;
                        }

                        $purchaseOrder->update(['total_value' => $total]);

                        return $purchaseOrder;
                    });

                    Notification::make()
                        ->title('Ordem de Compra gerada')
                        ->success()
                        ->send();

                    $this->redirect(PurchaseOrderResource::getUrl('edit', ['record' => $purchaseOrder]));
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->status === MaterialRequest::STATUS_RASCUNHO),
        ];
    }

    /**
     * Ver MaterialConsumptionService::notifyAdmins() -- mesmo motivo de
     * resolver a Role escopada por tenant em vez de User::role($nome) puro
     * (ignora tenant_id). Notifica admin (comportamento original, nao
     * removido -- tenant sem o papel novo nao perde o alerta) + Gestor de
     * Suprimentos (Fase 2: papel dedicado de aprovador de compras).
     */
    private function notifyAdmins(string $title): void
    {
        $tenantId = Tenancy::current()?->id;

        $this->notifyRole($tenantId, 'admin', $title);
        $this->notifyRole($tenantId, MaterialRequest::ROLE_GESTOR_SUPRIMENTOS, $title);
    }

    private function notifyRole(?string $tenantId, string $roleName, string $title): void
    {
        $role = Role::where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)->where('tenant_id', $tenantId)->get();

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title($title)
                ->body('Requisição aberta por '.($this->record->user?->name ?? '—').' — '.$this->record->items()->count().' item(ns).')
                ->warning()
                ->sendToDatabase($recipient);
        }
    }
}
