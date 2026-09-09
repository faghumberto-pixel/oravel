<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Support\Tenancy;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Tenancy::current()?->id;
        // PO avulsa (criada direto aqui, sem passar por uma Requisição já
        // aprovada) nasce em rascunho e precisa da sua própria aprovação --
        // ver App\Models\PurchaseOrder e EditMaterialRequest::gerar_ordem_compra
        // para o caminho normal, que nasce direto em "aprovada".
        $data['status'] = PurchaseOrder::STATUS_RASCUNHO;
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}
