<?php

namespace App\Console\Commands;

use App\Services\OnboardingMetricas;
use Illuminate\Console\Command;

/** Mostra as métricas do onboarding por solução no terminal. */
class MetricasOnboarding extends Command
{
    protected $signature = 'onboarding:metricas {--dias=30 : Período, em dias (0 = todo o período)}';

    protected $description = 'Métricas do onboarding por solução: conclusão por passo, em até 24 h e tempo mediano';

    public function handle(OnboardingMetricas $metricas): int
    {
        $dias = (int) $this->option('dias');

        foreach ($metricas->porSolucao($dias > 0 ? now()->subDays($dias) : null) as $m) {
            $this->line("<options=bold>{$m['titulo']}</> — {$m['assinaturas']} assinatura(s)".($dias > 0 ? " nos últimos {$dias} dias" : ''));

            foreach ($m['passos'] as $p) {
                $this->line("  {$p['titulo']}: {$p['concluiram']} ({$p['percentual']}%)");
            }

            $this->line("  Concluíram os passos: {$m['concluiram_todos']} ({$m['pct_concluiram_todos']}%)");
            $this->line("  Concluíram em até 24 h: {$m['concluiram_em_24h']} ({$m['pct_em_24h']}%)");
            $this->line('  Tempo mediano até concluir: '.OnboardingMetricas::duracao($m['mediana_minutos']));
        }

        return self::SUCCESS;
    }
}
