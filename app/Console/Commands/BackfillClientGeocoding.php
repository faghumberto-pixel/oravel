<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\CepGeocodingService;
use Illuminate\Console\Command;

/**
 * Comando avulso, nao entra no Kernel::schedule() -- roda uma vez pra
 * geocodificar Clientes que ja tinham endereco cadastrado antes do fix
 * de Client::$fillable (zip_code/state nunca salvavam) ou que so tem o
 * cep legado (coluna 'cep', sem campo no form, preenchida por seeder).
 * Alimenta o fallback de localizacao do Mapa de Equipamentos
 * (AssetMapWidget::getAssets()).
 */
class BackfillClientGeocoding extends Command
{
    protected $signature = 'clients:backfill-geocoding {--sleep=1 : Segundos de espera entre chamadas ao Nominatim (rate limit 1 req/s)}';

    protected $description = 'Geocodifica Clientes com endereço cadastrado (zip_code ou cep legado) mas ainda sem latitude/longitude';

    public function handle(): int
    {
        $service = app(CepGeocodingService::class);
        $sleep = (int) $this->option('sleep');

        $clients = Client::query()
            ->whereNull('latitude')
            ->where(function ($query) {
                $query->whereNotNull('zip_code')->orWhereNotNull('cep');
            })
            ->get();

        if ($clients->isEmpty()) {
            $this->info('Nenhum Cliente pendente de geocoding.');

            return Command::SUCCESS;
        }

        $this->info("Geocodificando {$clients->count()} Cliente(s)...");
        $bar = $this->output->createProgressBar($clients->count());

        $sucesso = 0;
        $falha = 0;

        foreach ($clients as $client) {
            $uf = $client->state ?: $client->uf;
            $fullAddress = trim(implode(', ', array_filter([$client->address, $client->city, $uf])));

            if (! $fullAddress) {
                $falha++;
                $bar->advance();

                continue;
            }

            $coords = $service->geocodeAddress($fullAddress);

            if ($coords) {
                $client->updateQuietly([
                    'latitude' => $coords['latitude'],
                    'longitude' => $coords['longitude'],
                ]);
                $sucesso++;
            } else {
                $falha++;
            }

            $bar->advance();

            if ($sleep > 0) {
                sleep($sleep);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Concluído: {$sucesso} geocodificado(s), {$falha} sem endereço suficiente ou não encontrado(s).");

        return Command::SUCCESS;
    }
}
