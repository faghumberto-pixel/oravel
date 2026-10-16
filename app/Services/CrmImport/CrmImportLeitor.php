<?php

namespace App\Services\CrmImport;

use App\Support\ExcelImport\XlsxReader;
use League\Csv\Reader;

/** Lê CSV (delimitador e codificação detectados) ou XLSX (1ª aba) para cabeçalhos + linhas. */
class CrmImportLeitor
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_LINHAS = 5000;

    /**
     * @return array{cabecalhos: array<int, string>, linhas: array<int, array<int, string>>}
     *                                                                                       linhas: número da linha no arquivo => valores por índice de coluna
     *
     * @throws \DomainException arquivo vazio, grande demais ou ilegível
     */
    public function ler(string $caminho, ?string $extensao = null): array
    {
        if (! is_file($caminho)) {
            throw new \DomainException('Arquivo não encontrado.');
        }

        if (filesize($caminho) > self::MAX_BYTES) {
            throw new \DomainException('O arquivo passa de 5 MB. Divida a planilha em partes menores.');
        }

        $extensao = strtolower($extensao ?: pathinfo($caminho, PATHINFO_EXTENSION));
        $registros = $extensao === 'xlsx' ? $this->lerXlsx($caminho) : $this->lerCsv($caminho);

        $cabecalhos = [];
        $linhas = [];
        $achouCabecalho = false;

        foreach ($registros as $numero => $valores) {
            $valores = array_map(fn ($v) => $this->texto($v), array_values($valores));

            if (! array_filter($valores, 'strlen')) {
                continue;
            }

            if (! $achouCabecalho) {
                foreach ($valores as $i => $nome) {
                    $cabecalhos[$i] = $nome !== '' ? $nome : 'Coluna '.($i + 1);
                }
                $achouCabecalho = true;

                continue;
            }

            $linhas[$numero] = $valores;
        }

        if (! $achouCabecalho) {
            throw new \DomainException('A planilha está vazia.');
        }

        if (! $linhas) {
            throw new \DomainException('A planilha só tem o cabeçalho, sem nenhuma linha de dados.');
        }

        if (count($linhas) > self::MAX_LINHAS) {
            throw new \DomainException('A planilha tem mais de '.self::MAX_LINHAS.' linhas. Divida em arquivos menores.');
        }

        return ['cabecalhos' => $cabecalhos, 'linhas' => $linhas];
    }

    private function texto(mixed $v): string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        if (is_float($v) && floor($v) === $v) {
            return (string) (int) $v;
        }

        return is_scalar($v) ? trim((string) $v) : '';
    }

    /** @return array<int, array<int, mixed>> */
    private function lerXlsx(string $caminho): array
    {
        return XlsxReader::rows($caminho, '');
    }

    /** @return array<int, array<int, mixed>> */
    private function lerCsv(string $caminho): array
    {
        $bruto = (string) file_get_contents($caminho);
        $bruto = preg_replace('/^\xEF\xBB\xBF/', '', $bruto);

        // Excel em português costuma salvar em Windows-1252.
        if (! mb_check_encoding($bruto, 'UTF-8')) {
            $bruto = mb_convert_encoding($bruto, 'UTF-8', 'Windows-1252');
        }

        $primeiraLinha = strtok($bruto, "\n") ?: '';
        $delimitador = collect([';' => substr_count($primeiraLinha, ';'), ',' => substr_count($primeiraLinha, ','), "\t" => substr_count($primeiraLinha, "\t")])
            ->sortDesc()->keys()->first();

        $leitor = Reader::createFromString($bruto);
        $leitor->setDelimiter($delimitador);

        $registros = [];
        foreach ($leitor->getRecords() as $indice => $registro) {
            $registros[$indice + 1] = $registro;
        }

        return $registros;
    }
}
