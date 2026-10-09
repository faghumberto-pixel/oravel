<?php

namespace App\Console\Commands;

use App\Models\EmailMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Evita que avisos e registros automáticos engordem o banco para sempre:
 *  - notificações já lidas com mais de 60 dias, e qualquer notificação com mais de 180 dias;
 *  - registros de e-mails enviados pelo sistema (pasta Automáticos da Caixa) com mais de 180 dias.
 * E-mails escritos por pessoas, mensagens de WhatsApp e demais dados NÃO são tocados.
 */
class LimparRegistrosAntigos extends Command
{
    protected $signature = 'registros:limpar {--dry-run : Só mostra quantos seriam apagados}';

    protected $description = 'Apaga notificações antigas e registros de e-mails automáticos do sistema';

    public function handle(): int
    {
        $simular = (bool) $this->option('dry-run');

        $lidas = DB::table('notifications')->whereNotNull('read_at')->where('read_at', '<', now()->subDays(60));
        $antigas = DB::table('notifications')->where('created_at', '<', now()->subDays(180));
        $emails = EmailMessage::withoutGlobalScopes()->withTrashed()->where('origem', 'sistema')->where('created_at', '<', now()->subDays(180));

        $contagem = ['notificações lidas (+60 dias)' => (clone $lidas)->count(), 'notificações (+180 dias)' => (clone $antigas)->count(), 'e-mails automáticos (+180 dias)' => (clone $emails)->count()];

        foreach ($contagem as $rotulo => $n) {
            $this->line(($simular ? '[simulação] ' : '').ucfirst($rotulo).": {$n}");
        }

        if (! $simular) {
            $lidas->delete();
            $antigas->delete();
            $emails->forceDelete();
        }

        return self::SUCCESS;
    }
}
