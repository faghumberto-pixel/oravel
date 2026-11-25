<?php

namespace App\Console\Commands;

use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Models\WebEvent;
use App\Models\WebPageview;
use Illuminate\Console\Command;

/**
 * Funil da página oravel.com.br/intelligence: visitou → clicou no CTA → abriu o formulário → enviou → cadastro concluído → primeiro upload.
 * Visitas e cliques vêm do rastreador do site (t.js); cadastro e upload vêm das contas de teste (fonte de verdade no servidor).
 */
class IntelligenceFunil extends Command
{
    protected $signature = 'intelligence:funil {--dias=30 : janela, em dias}';

    protected $description = 'Mostra o funil do teste grátis do Intelligence (página → cadastro → primeiro upload)';

    public function handle(): int
    {
        $desde = now()->subDays(max(1, (int) $this->option('dias')));

        $visitantes = fn (string $like) => WebEvent::where('type', 'click')->where('label', 'like', $like)->where('occurred_at', '>=', $desde)->distinct()->count('web_visit_id');

        $linhas = [
            ['Visualizaram a página', WebPageview::whereIn('path', ['/intelligence', '/intelligence/'])->where('entered_at', '>=', $desde)->distinct()->count('web_visit_id')],
            ['Clicaram no botão "Enviar minha planilha"', $visitantes('Teste grátis: CTA %')],
            ['Abriram o formulário', $visitantes('Teste grátis: abriu o formulário%')],
            ['Baixaram o modelo da planilha', $visitantes('Teste grátis: baixou o modelo%')],
            ['Enviaram o formulário', $visitantes('Teste grátis: enviou o formulário%')],
            ['Cadastro concluído (conta criada)', IntelligenceTrial::withoutGlobalScopes()->where('created_at', '>=', $desde)->count()],
            ['Primeiro upload concluído', IntelligenceTrial::withoutGlobalScopes()->where('created_at', '>=', $desde)->whereNotNull('last_upload_at')->count()],
        ];

        $this->info('Funil do teste grátis, últimos '.(int) $this->option('dias').' dias');
        $this->table(['Etapa', 'Pessoas'], $linhas);

        return self::SUCCESS;
    }
}
