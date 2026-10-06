<?php

namespace App\Console\Commands;

use App\Models\WebVisit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Apaga as estatísticas do site institucional mais antigas que N meses (a política de privacidade promete 13). */
class PruneWebAnalytics extends Command
{
    protected $signature = 'web-analytics:prune {--months=13 : Meses de histórico a manter}';

    protected $description = 'Apaga visitas do site institucional (web_visits e filhos) mais antigas que N meses';

    public function handle(): int
    {
        $cutoff = now()->subMonths(max(1, (int) $this->option('months')));
        $deleted = WebVisit::where('started_at', '<', $cutoff)->delete(); // cascata: pageviews e eventos

        DB::table('web_discards')->where('day', '<', $cutoff->toDateString())->delete();
        $this->info("{$deleted} visita(s) anterior(es) a {$cutoff->format('d/m/Y')} apagada(s).");

        return self::SUCCESS;
    }
}
