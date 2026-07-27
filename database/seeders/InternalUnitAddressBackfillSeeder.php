<?php

namespace Database\Seeders;

use App\Models\InternalUnit;
use App\Models\Tenant;
use App\Services\CepGeocodingService;
use Illuminate\Database\Seeder;

/**
 * Backfill de endereco/CEP das InternalUnit (matriz/filial) dos tenants demo
 * -- ate 2026-07-26 nenhuma tinha endereco preenchido, entao nunca viravam
 * origem valida de rota (Depot::syncFromInternalUnit() so' dispara quando ha
 * latitude/longitude) nem apareciam no Mapa de Equipamentos via cascata
 * Asset->Client->InternalUnit. CEPs reais de Campinas/SP (mesma cidade-base
 * usada em CompanyDemoSeeder/TorresGuindastesDemoSeeder pra todos os 5
 * tenants demo), verificados manualmente contra ViaCEP + Nominatim antes de
 * codificar aqui -- endereco fake nao teria geocodificado.
 *
 * Idempotente: pula unidade que ja tem CEP preenchido.
 */
class InternalUnitAddressBackfillSeeder extends Seeder
{
    /**
     * slug do tenant (Tenant::slug) => CEP real de Campinas/SP.
     *
     * @var array<string, string>
     */
    private const CEP_POR_TENANT_SLUG = [
        'geradores-rmc' => '13010-141',
        'torres-guindastes' => '13020-430',
        'eventos-show-geradores' => '13070-172',
        'construtora-alicerce-locacoes' => '13091-611',
        'hospital-vida-plena-energia' => '13083-970',
    ];

    public function run(CepGeocodingService $service): void
    {
        foreach (self::CEP_POR_TENANT_SLUG as $slug => $cep) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado, pulando.");

                continue;
            }

            $unit = InternalUnit::where('tenant_id', $tenant->id)->first();

            if (! $unit) {
                $this->command?->warn("Tenant '{$slug}' não tem InternalUnit, pulando.");

                continue;
            }

            if (filled($unit->cep)) {
                $this->command?->info("Pulando {$tenant->name} -- unidade já tem CEP.");

                continue;
            }

            $endereco = $service->lookupCep($cep);

            if (! $endereco) {
                $this->command?->warn("CEP {$cep} não resolveu no ViaCEP, pulando {$tenant->name}.");

                continue;
            }

            $enderecoCompleto = trim("{$endereco['address']}, {$endereco['city']} - {$endereco['uf']}");
            $coords = $service->geocodeAddress($enderecoCompleto);

            $unit->update([
                'cep' => $cep,
                'address' => $endereco['address'],
                'city' => $endereco['city'],
                'state' => $endereco['uf'],
                'latitude' => $coords['latitude'] ?? null,
                'longitude' => $coords['longitude'] ?? null,
            ]);

            $this->command?->info("{$tenant->name}: {$enderecoCompleto} ".($coords ? '(geolocalizado)' : '(sem coordenadas)'));
        }
    }
}
