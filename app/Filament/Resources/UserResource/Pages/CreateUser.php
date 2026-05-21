<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * 🔄 INTERCEPTADOR DE SALVAMENTO MULTI-TENANT
     * Modifica os dados antes de salvar no banco, garantindo que o novo funcionário
     * nasça com o tenant_id preenchido e seja vinculado à empresa locadora atual.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        
        if ($tenant) {
            // Se a sua tabela users tiver a coluna direta tenant_id
            $data['tenant_id'] = $tenant->id;
        }

        return $data;
    }

    /**
     * 🔗 VÍNCULO DA TABELA PIVOT POST-CREATE
     * Caso o seu sistema use o relacionamento Many-to-Many (pivot tenant_user),
     * este hook garante que o vínculo seja escrito logo após a criação do ID.
     */
    protected function afterCreate(): void
    {
        $tenant = Filament::getTenant();
        $user = $this->getRecord();

        if ($tenant && $user) {
            // Se houver o relacionamento 'tenants' no Model User, faz a sincronização física
            if (method_exists($user, 'tenants')) {
                $user->tenants()->syncWithoutDetaching([$tenant->id]);
            }
        }
    }

    /**
     * 🔄 REDIRECIONAMENTO SEGURO
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
