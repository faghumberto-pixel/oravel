<?php

namespace App\Relacionamento\Console;

use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Models\RelInteracao;
use Illuminate\Console\Command;

/**
 * Cumpre o prazo de guarda prometido na politica de privacidade do oravel.org: apaga o historico de USO DO SITE
 * (ferramentas, downloads, artigos, e-mails...) mais velho que N meses e as pessoas (rel_contatos) que ficaram sem
 * nenhuma interacao e sem atividade no periodo. NAO apaga 'contato_comercial': e' o registro da propria equipe da
 * Oravel (vem dos contatos de Prospect/SalesLead, que continuam la), nao dado de navegacao do titular.
 */
class PurgarRelacionamento extends Command
{
    protected $signature = 'relacionamento:purgar {--meses= : prazo de guarda (padrao: config relacionamento.retencao_meses)} {--dry-run : so conta, nao apaga}';

    protected $description = 'Apaga o historico de uso do site e contatos inativos além do prazo de guarda (LGPD)';

    public function handle(): int
    {
        $meses = (int) ($this->option('meses') ?: config('relacionamento.retencao_meses', 24));
        if ($meses < 1) {
            $this->error('O prazo de guarda precisa ser de pelo menos 1 mês.');

            return self::FAILURE;
        }

        $limite = now()->subMonths($meses);
        $dry = (bool) $this->option('dry-run');

        $interacoes = RelInteracao::where('ocorrido_em', '<', $limite)->where('tipo', '!=', RelInteracao::TIPO_CONTATO_COMERCIAL);
        $nInteracoes = (clone $interacoes)->count();
        if (! $dry) {
            $interacoes->delete();
        }

        // Depois de apagar as interacoes: pessoa sem nenhuma interacao restante e parada ha mais que o prazo.
        $contatos = RelContato::where('updated_at', '<', $limite)->whereDoesntHave('interacoes');
        $nContatos = (clone $contatos)->count();
        if (! $dry) {
            $contatos->delete();
        }

        $this->info(($dry ? '[dry-run] ' : '')."{$nInteracoes} interações e {$nContatos} contatos ".($dry ? 'seriam apagados' : 'apagados')." (guarda de {$meses} meses).");

        return self::SUCCESS;
    }
}
