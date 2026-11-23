<?php

namespace App\Support\ExcelImport;

use OpenSpout\Reader\XLSX\Reader;

/** Lê a aba pedida (ou a primeira) de um .xlsx: linha (1-based) => valores das células. */
class XlsxReader
{
    /** @return array<int, list<mixed>> */
    public static function rows(string $path, string $sheetName): array
    {
        $reader = new Reader;
        $reader->open($path);
        $picked = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            $picked ??= $sheet;
            if ($sheet->getName() === $sheetName) {
                $picked = $sheet;
                break;
            }
        }
        $rows = [];
        $line = 0;
        foreach ($picked?->getRowIterator() ?? [] as $row) {
            $line++;
            $rows[$line] = array_map(fn ($c) => $c->getValue(), $row->getCells());
        }
        $reader->close();

        return $rows;
    }

    /** Linhas da aba com ESSE nome, ou null se a planilha não tem a aba (diferente de rows(), que cai na primeira aba). */
    public static function rowsDaAba(string $path, string $sheetName): ?array
    {
        $reader = new Reader;
        $reader->open($path);
        $achada = false;
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() !== $sheetName) {
                continue;
            }
            $achada = true;
            $line = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $line++;
                $rows[$line] = array_map(fn ($c) => $c->getValue(), $row->getCells());
            }
            break;
        }
        $reader->close();

        return $achada ? $rows : null;
    }
}
