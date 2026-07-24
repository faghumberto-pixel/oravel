<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Popula Localizações (App\Models\Location -- cadastro simples de
 * endereço, independente de Branch/InternalUnit) nos 5 tenants de
 * demonstração. Idempotente, aditivo, sem criar tenant novo.
 *
 * Uso: php artisan db:seed --class=LocationDemoSeeder
 */
class LocationDemoSeeder extends Seeder
{
    private const LOCATIONS = [
        ['name' => 'Depósito de Peças', 'address' => 'Rua das Indústrias, 250', 'city' => 'Campinas', 'zip_code' => '13030-000'],
        ['name' => 'Ponto de Apoio -- Zona Sul', 'address' => 'Av. John Boyd Dunlop, 1500', 'city' => 'Campinas', 'zip_code' => '13060-000'],
    ];

    private const SLUGS = [
        'torres-guindastes',
        'geradores-rmc',
        'construtora-alicerce-locacoes',
        'eventos-show-geradores',
        'hospital-vida-plena-energia',
    ];

    public function run(): void
    {
        foreach (self::SLUGS as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            if (Location::where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            foreach (self::LOCATIONS as $data) {
                Location::create([
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                    'address' => $data['address'],
                    'city' => $data['city'],
                    'state' => 'SP',
                    'zip_code' => $data['zip_code'],
                ]);
            }
        }
    }
}
