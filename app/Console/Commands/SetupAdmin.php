<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class SetupAdmin extends Command
{
    protected $signature = 'oravel:setup-admin';
    protected $description = 'Configura o administrador global da Oravel';

    public function handle()
    {
        // 1. Criar a Role se não existir
        $role = Role::firstOrCreate(['name' => 'oravel_admin']);

        // 2. Criar ou buscar o admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@oravel.com'],
            [
                'name' => 'Administrador Oravel',
                'password' => Hash::make('password123'),
            ]
        );

        // 3. Atribuir a Role
        $admin->assignRole($role);

        $this->info('Administrador configurado com sucesso: admin@oravel.com / password123');
    }
}