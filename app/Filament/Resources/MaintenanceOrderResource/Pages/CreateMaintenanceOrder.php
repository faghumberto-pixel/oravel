<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Resources\MaintenanceOrderResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;
use App\Models\MaintenanceOrder;

class CreateMaintenanceOrder extends CreateRecord
{
    protected static string $resource = MaintenanceOrderResource::class;

    /**
     * 1. GERA O NÚMERO E ASSOCIA O TENANT ANTES DE CRIAR A OS
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $prefix = 'OS-' . date('Ym') . '-';
        
        // Busca a última OS para incrementar o sequencial
        $lastOrder = MaintenanceOrder::withoutGlobalScopes()
            ->withTrashed()
            ->where('os_number', 'like', $prefix . '%')
            ->orderBy('os_number', 'desc')
            ->first();
            
        $nextNumber = $lastOrder ? intval(substr($lastOrder->os_number, -4)) + 1 : 1;
        
        $data['os_number'] = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        // Define o status inicial (Pendente conforme sua regra)
        $data['status'] = $data['status'] ?? 'Aberto';
        
        /**
         * AJUSTE DE MULTI-TENANCY:
         * Filament::getTenant()->id é a forma recomendada de recuperar o UUID do inquilino atual.
         */
        $data['tenant_id'] = Filament::getTenant()->id;

        return $data;
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