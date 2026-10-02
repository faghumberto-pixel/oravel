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
}
