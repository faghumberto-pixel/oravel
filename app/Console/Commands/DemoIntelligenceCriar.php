<?php

namespace App\Console\Commands;

use App\Services\Demo\IntelligenceDemoService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DemoIntelligenceCriar extends Command
{
    protected $signature = 'demo:intelligence-criar {--senha= : Senha do administrador demo (padrão: gerada e mostrada uma vez)} {--apagar : Só apaga a empresa de teste}';

    protected $description = 'Cria (ou recria) a empresa "Intelligence Demo" com 3 meses de histórico, para testar o dashboard e o chat do Oravel Intelligence. Só mexe no tenant demo.';

    public function handle(IntelligenceDemoService $demo): int
    {
        try {
            if ($this->option('apagar')) {
                $r = $demo->apagar();
                $this->info($r['apagado'] ? "Intelligence Demo apagada ({$r['linhas']} linhas)." : 'Não existe empresa de teste para apagar.');

                return self::SUCCESS;
            }

            $senha = (string) ($this->option('senha') ?: Str::password(14, symbols: false));
            $r = $demo->criar($senha);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Intelligence Demo criada: {$r['snapshots']} fotografias (ago, set e out/2026); índice geral atual ".number_format((float) $r['indice_geral'], 0).'.');
        $this->line('Login: '.$r['email']);
        $this->line('Senha: '.$r['senha'].'  (mostrada só agora; para trocar, rode o comando de novo com --senha=)');

        return self::SUCCESS;
    }
}
