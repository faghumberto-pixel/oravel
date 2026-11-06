<?php

namespace App\Console\Commands;

use App\Services\Demo\LocadoraDemoManutencao;
use App\Services\Demo\LocadoraDemoService;
use Illuminate\Console\Command;

class DemoAlecManutencao extends Command
{
    protected $signature = 'demo:alec-manutencao';

    protected $description = 'Popula a Manutenção da Locadora Demo (agenda de hoje, OS recentes, esperando peça, técnicos e paradas). Só mexe no tenant demo; repetir recria os mesmos dados.';

    public function handle(LocadoraDemoService $demo, LocadoraDemoManutencao $manutencao): int
    {
        $tenant = $demo->tenant();
        if (! $tenant || ! $tenant->is_demo) {
            $this->error('A Locadora Demo não existe (rode demo:alec-criar antes) ou não está marcada como demo.');

            return self::FAILURE;
        }

        $this->info('Manutenção da Locadora Demo populada.');
        $this->table(['Item', 'Registros'], collect($manutencao->popular($tenant))->map(fn ($v, $k) => [str_replace('_', ' ', ucfirst($k)), $v])->values()->all());

        return self::SUCCESS;
    }
}
