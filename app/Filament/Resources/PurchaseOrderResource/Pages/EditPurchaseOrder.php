<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\GoodsReceiptResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // So' aparece pra PO avulsa (sem material_request_id) -- o
            // caminho normal (via "Gerar Ordem de Compra" em
            // EditMaterialRequest) ja' nasce em "aprovada", o gasto foi
            // autorizado la' atras na Requisicao.
            Actions\Action::make('enviar_aprovacao')
                ->label('Enviar para Aprovação')
                ->color('info')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $this->record->status === PurchaseOrder::STATUS_RASCUNHO && $this->record->items()->exists())
                ->action(function () {
                    $this->record->submitForApproval();
                    $this->notifyGestorSuprimentos('Ordem de Compra aguardando aprovação');

                    Notification::make()->title('Enviada para aprovação')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('aprovar')
                ->label('Aprovar')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === PurchaseOrder::STATUS_AGUARDANDO_APROVACAO
                    && auth()->user()?->can('update', $this->record))
                ->action(function () {
                    $this->record->approve(auth()->user());

                    Notification::make()->title('Ordem de Compra aprovada')->success()->send();
                    $this->refreshFormData(['status', 'approved_by_user_id', 'approved_at']);
                }),

            Actions\Action::make('recusar')
                ->label('Recusar')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === PurchaseOrder::STATUS_AGUARDANDO_APROVACAO
                    && auth()->user()?->can('update', $this->record))
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo da recusa')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->reject(auth()->user(), $data['reason']);

                    Notification::make()->title('Ordem de Compra recusada')->warning()->send();
                    $this->refreshFormData(['status', 'rejection_reason']);
                }),

            Actions\Action::make('enviar_fornecedor')
                ->label('Enviar ao Fornecedor')
                ->color('success')
                ->icon('heroicon-o-truck')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === PurchaseOrder::STATUS_APROVADA)
                ->action(function () {
                    $this->record->sendToSupplier();

                    Notification::make()->title('Ordem de Compra enviada ao fornecedor')->success()->send();
                    $this->refreshFormData(['status', 'sent_at']);
                }),

            Actions\Action::make('registrar_recebimento')
                ->label('Registrar Recebimento')
                ->color('success')
                ->icon('heroicon-o-inbox-arrow-down')
                ->url(fn () => GoodsReceiptResource::getUrl('create', ['purchase_order_id' => $this->record->id]))
                ->visible(fn () => in_array($this->record->status, [PurchaseOrder::STATUS_ENVIADA_FORNECEDOR, PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA], true)),

            Actions\Action::make('cancelar')
                ->label('Cancelar Ordem de Compra')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->visible(fn () => ! in_array($this->record->status, [PurchaseOrder::STATUS_RECEBIDA, PurchaseOrder::STATUS_CANCELADA], true))
                ->action(function () {
                    $this->record->cancel();

                    Notification::make()->title('Ordem de Compra cancelada')->warning()->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }

    /**
     * Mesmo mecanismo de EditMaterialRequest::notifyRole() -- Role
     * escopada por tenant, nao User::role() puro.
     */
    private function notifyGestorSuprimentos(string $title): void
    {
        $tenantId = Tenancy::current()?->id;

        $role = Role::where('name', PurchaseOrder::ROLE_GESTOR_SUPRIMENTOS)
            ->where('guard_name', 'web')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $role) {
            return;
        }

        foreach (User::role($role)->where('tenant_id', $tenantId)->get() as $recipient) {
            Notification::make()
                ->title($title)
                ->body('Ordem de Compra avulsa aberta por '.($this->record->createdBy?->name ?? '—').'.')
                ->warning()
                ->sendToDatabase($recipient);
        }
    }
}
