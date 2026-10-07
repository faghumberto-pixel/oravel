<?php

namespace App\Console\Commands;

use App\Services\Frota\GpsOdometroService;
use Illuminate\Console\Command;

class FrotaSincronizarGps extends Command
{
    protected $signature = 'frota:sincronizar-gps';

    protected $description = 'Soma ao odômetro dos veículos a distância percorrida registrada pelo GPS (Traccar)';

    public function handle(GpsOdometroService $servico): int
    {
        $r = $servico->sincronizarTodos();
        $this->info("GPS: {$r['aplicados']} veículo(s) atualizado(s) (+{$r['km']} km), {$r['sem_km']} sem km novo, {$r['falhas']} falha(s).");

        return self::SUCCESS;
    }
}
