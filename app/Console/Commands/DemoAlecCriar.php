<?php

namespace App\Console\Commands;

use App\Services\Demo\LocadoraDemoService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DemoAlecCriar extends Command
{
    protected $signature = 'demo:alec-criar {--senha= : Senha do administrador demo (padrão: gerada e mostrada uma vez)}';

    protected $description = 'Recria do zero a empresa "Locadora Demo" (demo para a ALEC): 25 equipamentos, contratos e 18 meses de manutenção. Só mexe no tenant demo.';

    public function handle(LocadoraDemoService $demo): int
    {
        $senha = (string) ($this->option('senha') ?: Str::password(14, symbols: false));

        try {
            $r = $demo->criar($senha);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Locadora Demo criada.');
        $this->table(['Item', 'Quantidade'], [
            ['Equipamentos', $r['equipamentos']], ['Clientes', $r['clientes']], ['Obras', $r['obras']],
            ['Contratos', $r['contratos']], ['Ordens de serviço', $r['ordens_de_servico']],
            ['Custo total de manutenção (R$)', number_format($r['custo_total'], 2, ',', '.')],
        ]);
        $this->line('Equipamentos problemáticos (custo acumulado / valor): '.implode(', ', $r['problematicos']));
        $this->newLine();
        $this->line('Login: '.$r['email']);
        $this->line('Senha: '.$r['senha'].'  (mostrada só agora; para trocar, rode o comando de novo com --senha=)');

        return self::SUCCESS;
    }
}
