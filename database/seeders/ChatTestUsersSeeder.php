<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use DB;

class ChatTestUsersSeeder extends Seeder
{
    public function run()
    {
        // 1. Tenta descobrir o tenant_id do utilizador com o qual estás a testar no painel.
        // Procura pelo primeiro utilizador cadastrado (provavelmente tu) para herdar o tenant_id correto.
        $adminUser = User::withoutGlobalScopes()->whereNotNull('tenant_id')->first();
        
        if (!$adminUser) {
            // Fallback caso a tabela de utilizadores use outra lógica, pega o primeiro da tabela de tenants
            $tenantId = DB::table('tenants')->value('id') ?? 1;
            $this->command->warn("Nenhum utilizador base encontrado. Usando Tenant ID padrão: " . $tenantId);
        } else {
            $tenantId = $adminUser->tenant_id;
            $this->command->info("Tenant detetado com base no utilizador [{$adminUser->email}]: ID {$tenantId}");
        }

        // 2. Define os utilizadores operacionais para o teste de campo
        $users = [
            [
                'name' => 'Alessandro (Técnico de Campo)',
                'email' => 'alessandro.tecnico@oravel.com.br',
                'password' => Hash::make('password'),
                'tenant_id' => $tenantId,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Marcos (Supervisor Manutenção)',
                'email' => 'marcos.supervisor@oravel.com.br',
                'password' => Hash::make('password'),
                'tenant_id' => $tenantId,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Ricardo (Gerente de Operações)',
                'email' => 'ricardo.gerente@oravel.com.br',
                'password' => Hash::make('password'),
                'tenant_id' => $tenantId,
                'email_verified_at' => now(),
            ],
        ];

        // 3. Injeta ou atualiza sem usar comandos destrutivos (regras de segurança mantidas)
        foreach ($users as $userData) {
            User::withoutGlobalScopes()->updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('Utilizadores de teste criados com sucesso e vinculados ao Tenant ID: ' . $tenantId);
    }
}
