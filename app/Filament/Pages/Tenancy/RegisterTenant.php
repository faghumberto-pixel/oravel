<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Tenant;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant as BaseRegisterTenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RegisterTenant extends BaseRegisterTenant
{
    public static function getLabel(): string
    {
        return 'Registrar Minha Empresa';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nome da Empresa')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),

                TextInput::make('slug')
                    ->label('Endereço Exclusivo (URL)')
                    ->required()
                    ->unique(Tenant::class, 'slug')
                    ->placeholder('ex: nome-da-empresa')
                    ->helperText('Este será o link de acesso da sua empresa.'),
            ]);
    }

    protected function handleRegistration(array $data): Tenant
    {
        // Define o status inicial como trial para novos registros via formulário
        $data['status'] = 'trial';
        
        $tenant = Tenant::create($data);

        $user = Auth::user();
        
        if ($user) {
            // Vincula o usuário ao tenant_id e define o papel de admin
            // Note que usamos 'tenant_id' para seguir o padrão do Filament
            $user->update([
                'tenant_id' => $tenant->id,
            ]);

            // Se você estiver usando o Spatie para Roles:
            // $user->assignRole('admin');
            
            // Garante o vínculo na tabela pivô do Filament (se existir a relação belongsToMany)
            if (method_exists($tenant, 'users')) {
                $tenant->users()->attach($user);
            }
        }

        return $tenant;
    }
}