<?php

namespace App\Console\Commands;

use App\Dashboards\Personalizacao\DashboardInteligenciaDeAtivos;
use App\Models\Tenant;
use Illuminate\Console\Command;

class DashboardsInteligenciaAtivos extends Command
{
    protected $signature = 'dashboards:inteligencia-ativos {--empresa= : Nome ou slug da empresa que recebe o dashboard} {--inicio : Com --empresa, vira a tela Início dela}';

    protected $description = 'Cria/atualiza o modelo "Inteligência de Ativos — custo e localização" (aparece na Central) e, opcionalmente, atribui a uma empresa';

    public function handle(): int
    {
        $empresa = null;
        if ($busca = $this->option('empresa')) {
            $empresa = Tenant::withoutGlobalScopes()->where('slug', $busca)->orWhere('name', $busca)->first();
            if (! $empresa) {
                $this->error('Empresa "'.$busca.'" não encontrada.');

                return self::FAILURE;
            }
        }

        $modelo = DashboardInteligenciaDeAtivos::garantirModelo();
        $this->info('Modelo "'.$modelo->name.'": '.count(DashboardInteligenciaDeAtivos::BLOCOS).' blocos (Central → Dashboards personalizados).');

        if ($empresa) {
            DashboardInteligenciaDeAtivos::atribuirA($empresa, (bool) $this->option('inicio'));
            $this->info('Atribuído a '.$empresa->name.($this->option('inicio') ? ' como Início' : '').'.');
        }

        return self::SUCCESS;
    }
}
