<?php

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\TenantResource;
use App\Services\TenantProvisioner;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected array $adminData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['features']) && !is_array($data['features'])) {
            $data['features'] = [];
        }

        $this->adminData = [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => $data['admin_password'],
        ];
        unset($data['admin_name'], $data['admin_email'], $data['admin_password']);

        return $data;
    }

    protected function afterCreate(): void
    {
        TenantProvisioner::provision($this->record, $this->adminData);
    }
}
