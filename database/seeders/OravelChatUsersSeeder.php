<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role; // Utiliza o seu modelo customizado que estende o Spatie
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class OravelChatUsersSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Captura o ID do tenant diretamente do usuário 31 (seu usuário logado no painel)
        $tenantId = DB::table('tenant_user')->where('user_id', 31)->value('tenant_id');

        // Fallback por aproximação caso a tabela pivô mude
        if (!$tenantId) {
            $tenant = Tenant::where('slug', 'like', '%innova%')
                ->orWhere('name', 'like', '%innova%')
                ->first();
            $tenantId = $tenant ? $tenant->id : Tenant::first()?->id;
        }

        if (!$tenantId) {
            $this->command->error('Nenhum Tenant ativo ou correspondente pôde ser localizado.');
            return;
        }

        $this->command->info("Configurando funções e equipe para o Tenant ID: {$tenantId}");

        // 2. Garante a criação das Roles (Funções) específicas DENTRO do escopo do Tenant atual
        $rolesParaCriar = ['tecnico', 'supervisor', 'gerente'];
        foreach ($rolesParaCriar as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenantId, // Essencial para o escopo do tenant no Spatie
            ]);
        }
        $this->command->info('Funções operacionais injetadas no Tenant com sucesso.');

        // 3. Define a massa de dados estruturada com a role e o tenant_id correspondentes
        $colaboradores = [
            [
                'name' => 'Carlos Mendoza (Técnico)',
                'email' => 'carlos.mendoza@oravel.com',
                'password' => Hash::make('password'),
                'tenant_id' => $tenantId,
                'role' => 'tecnico',
            ],
            [
                'name' => 'Adriel Souza (Técnico)',
                'email' => 'adriel.souza@oravel.com',
                'password' => Hash::make('password'),
                'tenant_id' => $tenantId,
                'role' => 'tecnico',
            ],
            [
                'name' => 'Bruno Pires (Supervisor)',
                'email' => 'bruno.pires@oravel.com',
                'password' => Hash::make('password'),
                'tenant_id' => $tenantId,
                'role' => 'supervisor',
            ],
            [
                'name' => 'Fernanda Lima (Gerente)',
                'email' => 'fernanda.lima@oravel.com',
                'password' => Hash::make('password'),
                'tenant_id' => $tenantId,
                'role' => 'gerente',
            ],
        ];

        // 4. Configura o Spatie para apontar para o Tenant correto durante a atribuição de permissões
        if (method_exists(app(\Spatie\Permission\PermissionRegistrar::class), 'setPermissionsTeamId')) {
            setPermissionsTeamId($tenantId);
        }

        foreach ($colaboradores as $dados) {
            // Força a atualização do tenant_id caso os e-mails já existam no banco no escopo errado
            $user = User::updateOrCreate(
                ['email' => $dados['email']],
                $dados
            );
            
            // Sincroniza o relacionamento N:N na tabela pivô do Filament
            $user->tenants()->syncWithoutDetaching([$tenantId]);

            // Atribui com segurança a Role do Spatie amarrada ao Tenant ID configurado
            try {
                if (!$user->hasRole($dados['role'])) {
                    $user->assignRole($dados['role']);
                }
            } catch (\Throwable $e) {
                $this->command->error("Erro ao atribuir a função para {$dados['name']}: " . $e->getMessage());
            }

            $this->command->line("<info>Sincronizado:</info> {$dados['name']} como {$dados['role']}");
        }

        $this->command->info('Massa de dados e hierarquia operacional semeadas com sucesso!');
    }
}
