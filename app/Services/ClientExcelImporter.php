<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Tenant;
use App\Support\AssetImport\AssetImportColumns as Norm;
use App\Support\ClientImport\ClientImportColumns as Cols;
use App\Support\ExcelImport\XlsxReader;
use App\Support\ClientDuplicidade;
use Illuminate\Support\Facades\DB;

/**
 * Importa clientes de uma planilha .xlsx no formato do modelo (ClientImportColumns).
 * Mesmas regras do importador de ativos: linhas com erro são puladas e reportadas, $dryRun só valida.
 * Duplicidade: pelo CNPJ/CPF (só dígitos); sem documento, pela Razão Social.
 */
class ClientExcelImporter
{
    /**
     * @return array{created:int, updated:int, skipped:int, errors:list<array{line:int, patrimonio:string, message:string}>, dry_run:bool}
     */
    public function import(string $path, Tenant $tenant, bool $updateExisting = false, bool $dryRun = false): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'dry_run' => $dryRun];
        $byTitle = [];
        foreach (Cols::all() as $col) {
            $byTitle[Norm::normalize($col['title'])] = $col;
        }

        $header = null;
        $map = [];
        $dataRows = [];
        foreach (XlsxReader::rows($path, Cols::SHEET) as $line => $cells) {
            if ($header === null) {
                $found = [];
                foreach ($cells as $i => $text) {
                    if (isset($byTitle[Norm::normalize($text)])) {
                        $found[$i] = $byTitle[Norm::normalize($text)];
                    }
                }
                if (collect($found)->contains(fn ($c) => $c['field'] === 'name')) {
                    $header = $line;
                    $map = $found;
                }

                continue;
            }
            if (collect($cells)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()) {
                $dataRows[$line] = $cells;
            }
        }

        if ($header === null) {
            $result['errors'][] = ['line' => 0, 'patrimonio' => '', 'message' => 'Cabeçalho não encontrado: use o modelo da Oravel (coluna "Razão Social" obrigatória).'];

            return $result;
        }

        // índices de duplicidade do cliente (empresa) — carregados uma vez
        $existing = Client::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get(['id', 'name', 'document']);
        $byDoc = $existing->filter(fn ($c) => $this->digits($c->document) !== '')->keyBy(fn ($c) => $this->digits($c->document));
        $byName = $existing->keyBy(fn ($c) => Norm::normalize($c->name));
        $seen = [];

        DB::beginTransaction();
        try {
            foreach ($dataRows as $line => $cells) {
                $label = '';
                DB::beginTransaction();
                try {
                    $data = [];
                    foreach ($map as $i => $col) {
                        $v = $this->parse($col, $cells[$i] ?? null);
                        if ($v !== null) {
                            $data[$col['field']] = $v;
                        }
                    }
                    $label = (string) ($data['name'] ?? '');
                    if ($label === '') {
                        throw new \DomainException('Razão Social é obrigatória.');
                    }

                    $doc = $this->digits($data['document'] ?? null);
                    $key = $doc !== '' ? 'd'.$doc : 'n'.Norm::normalize($label);
                    if (isset($seen[$key])) {
                        throw new \DomainException("Cliente repetido na planilha (primeira vez na linha {$seen[$key]}).");
                    }
                    $seen[$key] = $line;

                    $found = ($doc !== '' ? $byDoc->get($doc) : null) ?? ($doc === '' ? $byName->get(Norm::normalize($label)) : null);
                    if ($found && ! $updateExisting) {
                        DB::rollBack();
                        $result['skipped']++;

                        continue;
                    }

                    $conflitos = ClientDuplicidade::conflitos($data, $tenant->id, $found?->id);
                    if ($conflitos !== []) {
                        throw new \DomainException('Duplicado: '.implode(' ', array_values($conflitos)));
                    }

                    if ($found) {
                        Client::withoutGlobalScopes()->whereKey($found->id)->firstOrFail()->fill($data)->save();
                        $outcome = 'updated';
                    } else {
                        $client = new Client($data);
                        $client->tenant_id = $tenant->id;
                        $client->save();
                        $outcome = 'created';
                    }
                    DB::commit();
                    $result[$outcome]++;
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $result['errors'][] = ['line' => $line, 'patrimonio' => $label, 'message' => $e instanceof \DomainException ? $e->getMessage() : 'Erro inesperado: '.$e->getMessage()];
                }
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $result;
    }

    private function digits(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    private function parse(array $col, mixed $raw): mixed
    {
        $text = is_string($raw) ? trim($raw) : $raw;
        if ($text === null || $text === '') {
            return null;
        }
        $title = $col['title'];
        $str = is_float($text) && floor($text) === $text ? (string) (int) $text : (string) $text;

        switch ($col['type']) {
            case 'enum':
                $n = Norm::normalize($str);
                foreach ($col['enum'] as $label => $value) {
                    if (Norm::normalize($label) === $n || Norm::normalize((string) $value) === $n) {
                        return $value;
                    }
                }
                throw new \DomainException("{$title}: valor \"{$str}\" não reconhecido. Aceitos: ".implode(', ', array_keys($col['enum'])).'.');
            case 'email':
                if (! filter_var($str, FILTER_VALIDATE_EMAIL)) {
                    throw new \DomainException("{$title}: \"{$str}\" não é um e-mail válido.");
                }

                return mb_strtolower($str);
            case 'uf':
                $uf = mb_strtoupper($str);
                if (! preg_match('/^[A-Z]{2}$/', $uf)) {
                    throw new \DomainException("{$title}: use a sigla de 2 letras (veio \"{$str}\").");
                }

                return $uf;
            case 'doc':
                $d = $this->digits($str);
                if (! in_array(strlen($d), [11, 14], true)) {
                    throw new \DomainException("{$title}: \"{$str}\" não tem 11 (CPF) nem 14 (CNPJ) dígitos.");
                }

                return $str;
        }

        return $str;
    }
}
