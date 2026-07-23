<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Popula Empresas (App\Models\Company -- razão social/endereço da própria
 * empresa do tenant, hoje só um cadastro simples) nos 5 tenants de
 * demonstração. Idempotente, aditivo, sem criar tenant novo.
 *
 * Uso: php artisan db:seed --class=CompanyDemoSeeder
 */
class CompanyDemoSeeder extends Seeder
{
    private const COMPANIES_BY_SLUG = [
        'torres-guindastes' => ['name' => 'Torres & Guindastes Locação de Equipamentos Ltda', 'city' => 'Campinas', 'phone' => '(19) 3221-4455'],
        'geradores-rmc' => ['name' => 'Geradores RMC Comércio e Locação Ltda', 'city' => 'Campinas', 'phone' => '(19) 3225-6677'],
        'construtora-alicerce-locacoes' => ['name' => 'Alicerce Locações de Equipamentos para Construção Ltda', 'city' => 'Campinas', 'phone' => '(19) 3231-8899'],
        'eventos-show-geradores' => ['name' => 'Eventos Show Locação de Geradores Ltda', 'city' => 'Campinas', 'phone' => '(19) 3241-1122'],
        'hospital-vida-plena-energia' => ['name' => 'Vida Plena Energia Crítica Ltda', 'city' => 'Campinas', 'phone' => '(19) 3251-3344'],
    ];

    public function run(): void
    {
        foreach (self::COMPANIES_BY_SLUG as $slug => $data) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            if (Company::where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            Company::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'address' => 'Av. Eng. Antônio Francisco de Paula Souza, s/n -- Jardim São Vicente',
                'city' => $data['city'],
                'state' => 'SP',
                'phone' => $data['phone'],
            ]);
        }
    }
}
