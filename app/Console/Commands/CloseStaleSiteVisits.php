<?php

namespace App\Console\Commands;

use App\Models\SiteVisit;
use Illuminate\Console\Command;

/**
 * Marca ended_at nas sessoes de site_visits sem atividade ha' mais de 30min
 * (mesmo limiar de inatividade usado por TrackSiteVisit pra abrir sessao
 * nova). Update em lote, sem tocar duration_seconds (ja congelado no ultimo
 * hit real) -- so' separa "sessao ainda ativa agora" de "sessao encerrada"
 * pros widgets do painel central.
 */
class CloseStaleSiteVisits extends Command
{
    protected $signature = 'site-visits:close-stale';

    protected $description = 'Fecha sessoes de site_visits inativas ha mais de 30 minutos';

    public function handle(): int
    {
        $affected = SiteVisit::whereNull('ended_at')
            ->where('last_activity_at', '<', now()->subMinutes(30))
            ->update(['ended_at' => now()]);

        $this->info("{$affected} sessão(ões) de visita encerrada(s) por inatividade.");

        return Command::SUCCESS;
    }
}
