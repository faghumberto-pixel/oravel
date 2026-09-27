<?php

namespace App\Console\Commands;

use App\Models\SiteVisit;
use Illuminate\Console\Command;

/**
 * Mantém só os N registros mais recentes de site_visits, apagando o resto
 * (pedido do usuário 2026-09-27: "os logs sejam apagados, fiquem só os
 * últimos 50"). Agendado (ver Kernel::schedule()) pra rodar sozinho, senão
 * a tabela volta a crescer sem limite -- é o mesmo dado usado pelos
 * widgets/relatórios de acesso do painel Central, então isso limita
 * também o histórico disponível ali pra só os 50 mais recentes.
 */
class PruneSiteVisits extends Command
{
    protected $signature = 'site-visits:prune {--keep=50 : Quantos registros mais recentes manter}';

    protected $description = 'Apaga site_visits antigos, mantendo só os N mais recentes';

    public function handle(): int
    {
        $keep = (int) $this->option('keep');

        $cutoffId = SiteVisit::orderByDesc('started_at')
            ->skip($keep - 1)
            ->take(1)
            ->value('id');

        if (! $cutoffId) {
            $this->info('Nada pra apagar -- menos de '.$keep.' registros no total.');

            return Command::SUCCESS;
        }

        $cutoffStartedAt = SiteVisit::find($cutoffId)->started_at;

        $deleted = SiteVisit::where('started_at', '<', $cutoffStartedAt)->delete();

        $this->info("{$deleted} registro(s) de site_visits apagado(s), mantidos os {$keep} mais recentes.");

        return Command::SUCCESS;
    }
}
