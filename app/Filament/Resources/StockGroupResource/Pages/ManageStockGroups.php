<?php

namespace App\Filament\Resources\StockGroupResource\Pages;

use App\Filament\Resources\StockGroupResource;
use App\Services\Estoque\EstoqueGrupos;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageStockGroups extends ManageRecords
{
    protected static string $resource = StockGroupResource::class;

    public function mount(): void
    {
        // Primeira abertura: cada empresa recebe os grupos padrao (Administracao, Equipamentos, Veiculos, Manutencao, Logistica, Outros).
        if ($tenantId = auth()->user()?->tenant_id) {
            app(EstoqueGrupos::class)->garantirPadrao((string) $tenantId);
        }
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Novo grupo')];
    }
}
