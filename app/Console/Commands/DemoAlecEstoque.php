<?php

namespace App\Console\Commands;

use App\Services\Demo\LocadoraDemoEstoque;
use App\Services\Demo\LocadoraDemoService;
use Illuminate\Console\Command;

class DemoAlecEstoque extends Command
{
    protected $signature = 'demo:alec-estoque';

    protected $description = 'Popula o estoque estruturado da Locadora Demo (grupos, endereços, peças e óleo, itens agregados e contagens com histórico). Só mexe no tenant demo; repetir recria os mesmos dados.';

    public function handle(LocadoraDemoService $demo, LocadoraDemoEstoque $estoque): int
    {
        $tenant = $demo->tenant();
        if (! $tenant || ! $tenant->is_demo) {
            $this->error('A Locadora Demo não existe (rode demo:alec-criar antes) ou não está marcada como demo.');

            return self::FAILURE;
        }

        $this->info('Estoque estruturado da Locadora Demo populado.');
        $this->table(['Item', 'Registros'], collect($estoque->popular($tenant))->map(fn ($v, $k) => [str_replace('_', ' ', ucfirst($k)), $v])->values()->all());

        return self::SUCCESS;
    }
}
