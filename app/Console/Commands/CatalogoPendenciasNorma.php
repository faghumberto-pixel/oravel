<?php

namespace App\Console\Commands;

use App\Services\Catalogo\PendenciasDeNorma;
use Illuminate\Console\Command;

class CatalogoPendenciasNorma extends Command
{
    protected $signature = 'catalogo:pendencias-norma {--md : grava docs/G1_VALIDACAO_NORMAS.md (checklist para o responsavel tecnico)}';

    protected $description = 'G1: lista tudo que o responsavel tecnico precisa validar (normas e intervalos provisorios) antes de ligar a chave em cliente real';

    public function handle(PendenciasDeNorma $pendencias): int
    {
        if ($this->option('md')) {
            file_put_contents(base_path('docs/G1_VALIDACAO_NORMAS.md'), $pendencias->markdown());
            $this->info('docs/G1_VALIDACAO_NORMAS.md atualizado.');
        }

        $linhas = $pendencias->levantar();
        $this->table(['Grupo', 'Tipo', 'Item', 'Norma'], array_map(fn ($l) => [$l['grupo'], $l['tipo'], $l['item'], $l['norma']], $linhas));
        $this->warn(count($linhas).' item(ns) a validar com o responsável técnico antes de ligar a chave em cliente real.');

        return self::SUCCESS;
    }
}
