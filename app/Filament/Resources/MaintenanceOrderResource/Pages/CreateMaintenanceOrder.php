<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Resources\MaintenanceOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceOrder extends CreateRecord
{
    protected static string $resource = MaintenanceOrderResource::class;

    // 1. GERA O NÚMERO E ASSOCIA O TENANT ANTES DE CRIAR A OS
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $prefix = 'OS-' . date('Ym') . '-';
        
        $lastOrder = \App\Models\MaintenanceOrder::withoutGlobalScopes()
            ->withTrashed()
            ->where('os_number', 'like', $prefix . '%')
            ->orderBy('os_number', 'desc')
            ->first();
            
        $nextNumber = $lastOrder ? intval(substr($lastOrder->os_number, -4)) + 1 : 1;
        
        $data['os_number'] = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        $data['status'] = 'Pendente';
        
        // AJUSTE CRÍTICO: Garante que a OS pertence ao seu Inquilino (Resolve o Erro 404)
        $data['tenant_id'] = auth()->user()->tenant_id;

        return $data;
    }

    // 2. REDIRECIONA PARA A EDIÇÃO APÓS SALVAR
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}