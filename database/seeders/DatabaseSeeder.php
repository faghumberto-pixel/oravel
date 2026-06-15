<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Executa a Seeder específica para a Nova Locadora
        $this->call([
            NovaLocadoraSeeder::class,
        ]);
    }
}
