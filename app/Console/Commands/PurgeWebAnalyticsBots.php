<?php

namespace App\Console\Commands;

use App\Models\WebVisit;
use App\Services\IpGeolocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Limpeza única das visitas do site institucional gravadas ANTES do filtro
 * por IP de datacenter (SiteTrackingController): reconsulta cada IP e apaga
 * as visitas cujo IP é de nuvem/servidor (robôs, pré-visualização de links).
 * Por padrão só LISTA (dry-run); --apply apaga de verdade.
 */
class PurgeWebAnalyticsBots extends Command
{
    protected $signature = 'web-analytics:purge-bots {--apply : Apaga de verdade (sem isso só lista)}';

    protected $description = 'Apaga visitas do site institucional vindas de IP de datacenter/nuvem (robôs)';

    public function handle(IpGeolocationService $geo): int
    {
        $ips = WebVisit::query()->whereNotNull('ip_address')->distinct()->pluck('ip_address');
        $bots = [];

        foreach ($ips as $ip) {
            $cached = Cache::has("ip-geo:{$ip}");
            if ($geo->locate($ip)['hosting'] ?? false) {
                $bots[] = $ip;
            }
            if (! $cached) {
                usleep(1_600_000); // ip-api grátis: 45 consultas/min
            }
        }

        $visits = WebVisit::whereIn('ip_address', $bots);
        $count = (clone $visits)->count();

        foreach ($bots as $ip) {
            $this->line("  robô: {$ip} (".WebVisit::where('ip_address', $ip)->count().' visita(s))');
        }

        if (! $this->option('apply')) {
            $this->info("{$count} visita(s) de robô encontrada(s) em ".count($bots).' IP(s). Nada apagado (use --apply).');

            return self::SUCCESS;
        }

        $visits->delete(); // cascata: pageviews e eventos
        $this->info("{$count} visita(s) de robô apagada(s).");

        return self::SUCCESS;
    }
}
