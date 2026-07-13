<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\MaintenanceOrderResource;
use App\Filament\Resources\MaintenanceOrderResource\Concerns\StoresPhotoEvidence;
use App\Models\MaintenanceOrder;
use App\Models\SolicitacaoLocacao;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('maintenance')]
class CreateMaintenanceOrder extends CreateRecord
{
    use StoresPhotoEvidence;

    protected static string $resource = MaintenanceOrderResource::class;

    /**
     * 1. GERA O NÚMERO E ASSOCIA O TENANT ANTES DE CRIAR A OS
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $prefix = 'OS-'.date('Ym').'-';

        // Busca a última OS para incrementar o sequencial
        $lastOrder = MaintenanceOrder::withoutGlobalScopes()
            ->withTrashed()
            ->where('os_number', 'like', $prefix.'%')
            ->orderBy('os_number', 'desc')
            ->first();

        $nextNumber = $lastOrder ? intval(substr($lastOrder->os_number, -4)) + 1 : 1;

        $data['os_number'] = $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        // Define o status inicial (Pendente conforme sua regra)
        $data['status'] = $data['status'] ?? 'Aberto';

        /**
         * AJUSTE DE MULTI-TENANCY:
         * \App\Support\Tenancy::current()->id é a forma recomendada de recuperar o UUID do inquilino atual.
         */
        $data['tenant_id'] = Tenancy::current()->id;

        // photo_before/photo_after não são colunas de maintenance_orders -- viram
        // registros em Attachment (evidences) depois que a OS existe.
        $this->extractPhotoEvidences($data);

        return $data;
    }

    /**
     * Bloqueia criar OS num ativo com Locacao urgente aguardando (mesma
     * condicao do banner do Kanban) sem autorizacao de um Supervisor de
     * Manutencao -- so na criacao (editar uma OS ja existente nesse ativo
     * nao e' bloqueada, pode ser a propria que vai liberar a reserva).
     */
    protected function beforeCreate(): void
    {
        $rawState = $this->form->getRawState();
        $assetId = $rawState['asset_id'] ?? null;
        $tenantId = Tenancy::current()?->id;

        if (! $assetId || ! $tenantId) {
            return;
        }

        if (! in_array($assetId, SolicitacaoLocacao::assetIdsComReservaUrgente($tenantId), true)) {
            return;
        }

        if (SolicitacaoLocacao::autorizaOverrideReserva($tenantId, $rawState['supervisor_override_password'] ?? null)) {
            return;
        }

        Notification::make()
            ->title('Bloqueado: ativo reservado por Locação urgente')
            ->body('Informe a senha de um Supervisor de Manutenção pra autorizar a criação da OS nesse ativo.')
            ->danger()
            ->send();

        $this->halt();
    }

    protected function afterCreate(): void
    {
        $this->persistPhotoEvidences($this->record);

        if ($this->record->technician_id && ($technician = User::find($this->record->technician_id))) {
            Notification::make()
                ->title('Nova OS atribuída a você')
                ->body('OS #'.$this->record->os_number.' foi criada e atribuída a você.')
                ->icon('heroicon-o-wrench-screwdriver')
                ->iconColor('info')
                ->actions([
                    Action::make('ver')
                        ->label('Ver OS')
                        ->url(MaintenanceOrderResource::getUrl('edit', ['record' => $this->record]))
                        ->button(),
                ])
                ->sendToDatabase($technician);
        }
    }

    /**
     * 2. REDIRECIONA PARA A EDIÇÃO APÓS SALVAR
     * Útil para que o técnico já caia na tela de "Apontamentos" ou "Checklist"
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
