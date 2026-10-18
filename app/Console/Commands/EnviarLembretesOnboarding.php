<?php

namespace App\Console\Commands;

use App\Services\OnboardingLembretes;
use Illuminate\Console\Command;

/** Envia os lembretes de onboarding que cabem agora (dia útil, 9h às 18h em São Paulo). Cada lembrete sai uma vez por empresa. */
class EnviarLembretesOnboarding extends Command
{
    protected $signature = 'onboarding:lembretes {--ignorar-janela : Envia mesmo fora do horário comercial (só para teste)}';

    protected $description = 'Lembretes por e-mail para empresas que ainda não concluíram o onboarding';

    public function handle(OnboardingLembretes $lembretes): int
    {
        $r = $lembretes->executar(null, (bool) $this->option('ignorar-janela'));

        $this->info('Lembretes enviados: '.collect($r)->map(fn ($n, $t) => "{$t}={$n}")->implode(', '));

        return self::SUCCESS;
    }
}
