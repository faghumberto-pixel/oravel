<?php

namespace App\Console\Commands;

use App\Services\Demo\LocadoraDemoComercial;
use App\Services\Demo\LocadoraDemoService;
use Illuminate\Console\Command;

class DemoAlecComercial extends Command
{
    protected $signature = 'demo:alec-comercial';

    protected $description = 'Popula o Comercial da Locadora Demo (leads, orçamentos, propostas, solicitações, Voz do Cliente e mais clientes). Só mexe no tenant demo; repetir recria os mesmos dados.';

    public function handle(LocadoraDemoService $demo, LocadoraDemoComercial $comercial): int
    {
        $tenant = $demo->tenant();
        if (! $tenant || ! $tenant->is_demo) {
            $this->error('A Locadora Demo não existe (rode demo:alec-criar antes) ou não está marcada como demo.');

            return self::FAILURE;
        }

        $r = $comercial->popular($tenant);

        $this->info('Comercial da Locadora Demo populado.');
        $this->table(['Item', 'Registros'], collect($r)->map(fn ($v, $k) => [str_replace('_', ' ', ucfirst($k)), $v])->values()->all());

        return self::SUCCESS;
    }
}
