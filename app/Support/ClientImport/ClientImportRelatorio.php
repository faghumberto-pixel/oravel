<?php

namespace App\Support\ClientImport;

/** Resumo em português e arquivo das linhas rejeitadas de uma importação de clientes. */
class ClientImportRelatorio
{
    /** Ex.: "28 importados, 2 com erro (linhas 7 e 12)". */
    public static function resumo(array $r): string
    {
        $erros = $r['errors'] ?? [];
        $importados = ($r['created'] ?? 0) + ($r['updated'] ?? 0);
        $texto = $importados.' '.($importados === 1 ? 'importado' : 'importados');

        if (($r['skipped'] ?? 0) > 0) {
            $texto .= ', '.$r['skipped'].' já '.($r['skipped'] === 1 ? 'existia' : 'existiam');
        }

        if ($erros !== []) {
            $linhas = array_values(array_filter(array_map(fn ($e) => (int) $e['line'], $erros)));
            $texto .= ', '.count($erros).' com erro';

            if ($linhas !== []) {
                $mostradas = array_slice($linhas, 0, 6);
                $ultima = array_pop($mostradas);
                $lista = $mostradas === [] ? (string) $ultima : implode(', ', $mostradas).' e '.$ultima;
                $texto .= ' (linha'.(count($mostradas) ? 's ' : ' ').$lista.(count($linhas) > 6 ? ' e outras' : '').')';
            }
        }

        return $texto;
    }

    /** CSV (; e UTF-8 com BOM, abre direto no Excel) com Linha, Motivo e as colunas da planilha, só com as linhas rejeitadas. */
    public static function csvDasRejeitadas(array $rejeitadas): string
    {
        $titulos = array_column(ClientImportColumns::all(), 'title');
        $saida = fopen('php://temp', 'r+');
        fputcsv($saida, array_merge(['Linha', 'Motivo do erro'], $titulos), ';');

        foreach ($rejeitadas as $linha) {
            fputcsv($saida, array_merge([$linha['line'], $linha['message']], array_map(fn ($t) => (string) ($linha['cells'][$t] ?? ''), $titulos)), ';');
        }

        rewind($saida);

        return "\xEF\xBB\xBF".stream_get_contents($saida);
    }
}
