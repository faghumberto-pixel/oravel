<?php

namespace App\Services\ProspectImport;

use App\Services\CrmImport\CrmImportLeitor;

/**
 * Le o arquivo de prospects: CSV e XLSX (mesmo leitor do importador do CRM) e JSON (lista de objetos, como as
 * listas exportadas das pesquisas). Sempre devolve cabecalhos + linhas, pra o mapeamento de colunas ser um so.
 */
class ProspectImportLeitor
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_LINHAS = 5000;

    /**
     * @return array{cabecalhos: array<int, string>, linhas: array<int, array<int, string>>}
     *
     * @throws \DomainException
     */
    public function ler(string $caminho, ?string $extensao = null): array
    {
        $extensao = strtolower($extensao ?: pathinfo($caminho, PATHINFO_EXTENSION));

        if ($extensao !== 'json') {
            return app(CrmImportLeitor::class)->ler($caminho, $extensao);
        }

        if (! is_file($caminho)) {
            throw new \DomainException('Arquivo não encontrado.');
        }

        if (filesize($caminho) > self::MAX_BYTES) {
            throw new \DomainException('O arquivo passa de 5 MB. Divida a lista em partes menores.');
        }

        $dados = json_decode((string) file_get_contents($caminho), true);

        // Aceita a lista direto ([{...}]) ou embrulhada ({"data": [...]}, {"items": [...]}, {"prospects": [...]}).
        if (is_array($dados) && ! array_is_list($dados)) {
            foreach (['data', 'items', 'prospects', 'empresas', 'results'] as $chave) {
                if (isset($dados[$chave]) && is_array($dados[$chave])) {
                    $dados = $dados[$chave];
                    break;
                }
            }
        }

        if (! is_array($dados) || $dados === [] || ! array_is_list($dados) || ! is_array($dados[0] ?? null)) {
            throw new \DomainException('O JSON precisa ser uma lista de empresas, por exemplo [{"cnpj": "...", "nome": "..."}].');
        }

        if (count($dados) > self::MAX_LINHAS) {
            throw new \DomainException('A lista tem mais de '.self::MAX_LINHAS.' empresas. Divida em arquivos menores.');
        }

        $cabecalhos = [];
        foreach ($dados as $item) {
            foreach (array_keys((array) $item) as $chave) {
                if (! in_array((string) $chave, $cabecalhos, true)) {
                    $cabecalhos[] = (string) $chave;
                }
            }
        }

        $linhas = [];
        foreach ($dados as $i => $item) {
            $item = (array) $item;
            $linhas[$i + 1] = array_map(fn (string $c) => $this->texto($item[$c] ?? null), $cabecalhos);
        }

        return ['cabecalhos' => $cabecalhos, 'linhas' => $linhas];
    }

    private function texto(mixed $v): string
    {
        return match (true) {
            is_bool($v) => $v ? 'sim' : 'não',
            is_float($v) && floor($v) === $v => (string) (int) $v,
            is_scalar($v) => trim((string) $v),
            default => '',
        };
    }
}
