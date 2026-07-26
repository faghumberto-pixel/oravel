<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Tenant;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant as BaseRegisterTenant;

class RegisterTenant extends BaseRegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register tenant';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Seu schema aqui
            ]);
    }

    protected function handleRegistration(array $data): Tenant
    {
        $tenant = Tenant::create($data);

        return $tenant;
    }
}
