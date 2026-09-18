<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cria Clients (Portal do Cliente) para os tenants de demonstração.
 * Separado de DemoTenantsSeeder pois aquele cria Users (painel admin),
 * este cria Clients (portal cliente).
 *
 * Uso: php artisan db:seed --class=DemoPortalClientsSeeder
 *
 * Credenciais padrão para todos: email=admin@{slug}.com.br, senha=Demoloravel!*
 */
class DemoPortalClientsSeeder extends Seeder
{
    public function run(): void
    {
        // Slugs dos tenants de demo (devem existir do DemoTenantsSeeder)
        $demoCoresTenants = [
            'munkmaq',
            'tecnogen',
            'cunzolo',
            'superinfra',
            'rmc-plataformas',
            'geradores-campinas',
            'lomaq',
            'lma-locacoes',
        ];

        foreach ($demoCoresTenants as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");
                continue;
            }

            // Verificar se Client já existe
            $email = "admin@{$slug}.com.br";
            if (Client::where('email', $email)->exists()) {
                $this->command?->info("Client '{$email}' já existe -- pulando.");
                continue;
            }

            // Criar Client com credenciais de demo
            Client::create([
                'tenant_id' => $tenant->id,
                'name' => 'Admin '.$tenant->name,
                'email' => $email,
                'password' => Hash::make('Demoloravel!*'),
                'portal_access_enabled_at' => now(),
            ]);

            $this->command?->info("✅ Client criado: {$email}");
        }

        $this->command?->info('DemoPortalClientsSeeder concluído -- Clients de portal prontos.');
    }
}
