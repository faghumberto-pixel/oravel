<?php

namespace App\Console\Commands;

use App\Services\Demo\LocadoraDemoOperacional;
use App\Services\Demo\LocadoraDemoService;
use Illuminate\Console\Command;

class DemoAlecOperacional extends Command
{
    protected $signature = 'demo:alec-operacional';

    protected $description = 'Popula Materiais, Financeiro, Frota e Logística da Locadora Demo. Só mexe no tenant demo; repetir recria os mesmos dados.';

    public function handle(LocadoraDemoService $demo, LocadoraDemoOperacional $operacional): int
    {
        $tenant = $demo->tenant();
        if (! $tenant || ! $tenant->is_demo) {
            $this->error('A Locadora Demo não existe (rode demo:alec-criar antes) ou não está marcada como demo.');

            return self::FAILURE;
        }

        foreach ($operacional->popular($tenant) as $area => $contagens) {
            $this->info(ucfirst($area));
            $this->table(['Item', 'Registros'], collect($contagens)->map(fn ($v, $k) => [str_replace('_', ' ', ucfirst($k)), $v])->values()->all());
        }

        return self::SUCCESS;
    }
}
