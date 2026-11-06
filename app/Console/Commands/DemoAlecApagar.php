<?php

namespace App\Console\Commands;

use App\Services\Demo\LocadoraDemoService;
use Illuminate\Console\Command;

class DemoAlecApagar extends Command
{
    protected $signature = 'demo:alec-apagar {--force : Não pede confirmação}';

    protected $description = 'Apaga a empresa "Locadora Demo" e todos os dados dela. Nunca toca em outro tenant (recusa se o tenant não estiver marcado como demo).';

    public function handle(LocadoraDemoService $demo): int
    {
        $tenant = $demo->tenant();
        if (! $tenant) {
            $this->info('A Locadora Demo não existe: nada a apagar.');

            return self::SUCCESS;
        }

        if (! $tenant->is_demo) {
            $this->error('A empresa "'.LocadoraDemoService::SLUG.'" existe mas NÃO está marcada como demo: nada foi apagado.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Apagar a Locadora Demo e todos os dados dela?')) {
            return self::FAILURE;
        }

        try {
            $r = $demo->apagar();
        } catch (\Throwable $e) {
            $this->error('Falhou e nada foi apagado: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Locadora Demo apagada ('.$r['linhas'].' linhas removidas).');

        return self::SUCCESS;
    }
}
