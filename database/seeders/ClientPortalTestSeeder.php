<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClientPortalTestSeeder extends Seeder
{
    public function run(): void
    {
        // Criar tenant demo simples
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'cliente-teste'],
            [
                'name' => 'Cliente Teste Portal',
            ]
        );

        echo "✅ Tenant criado: {$tenant->slug}\n";

        // Criar cliente para portal
        $client = Client::updateOrCreate(
            ['email' => 'admin@cliente-teste.com.br'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin Cliente Teste',
                'password' => Hash::make('TestDemo2026'),
                'portal_access_enabled_at' => now(),
            ]
        );

        echo "✅ Cliente criado: {$client->email}\n";
        echo "   Senha: TestDemo2026\n";
    }
}
