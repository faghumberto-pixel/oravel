<?php

namespace Database\Seeders;

use App\Models\User;
// REMOVA ESTA LINHA: use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // REMOVA ESTA LINHA: use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Você pode manter ou remover estas linhas de User::factory(), dependendo se você quer o usuário de teste.
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // ADICIONE ESTA LINHA para chamar o seu TenantSeeder
        $this->call(TenantSeeder::class);
    }
}