<?php

namespace App\Services;

use App\Domain\Fleet\Models\ForkliftSpecification;
use App\Domain\Fleet\Models\GeneratorSpecification;
use App\Domain\Fleet\Models\PlatformSpecification;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetNr13Specification;
use App\Models\InternalUnit;
use App\Models\StorageLocation;
use App\Models\Tenant;
use App\Support\AssetImport\AssetImportColumns as Cols;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Importa ativos de uma planilha .xlsx no formato do modelo (AssetImportColumns).
 * Linhas com erro são puladas e reportadas; as demais são gravadas. Com $dryRun tudo roda
 * dentro de uma transação que é desfeita no fim (só valida).
 */
class AssetExcelImporter
{
    private const SPEC_MODELS = [
        'forklift' => [ForkliftSpecification::class, 'forkliftSpecification'],
        'platform' => [PlatformSpecification::class, 'platformSpecification'],
        'generator' => [GeneratorSpecification::class, 'generatorSpecification'],
        'nr13' => [AssetNr13Specification::class, 'nr13Specification'],
    ];

    /** @var array<string, string> */
    private array $categoryCache = [];

    /**
     * @return array{created:int, updated:int, skipped:int, errors:list<array{line:int, patrimonio:string, message:string}>, dry_run:bool}
     */
    public function import(string $path, Tenant $tenant, bool $updateExisting = false, bool $dryRun = false): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'dry_run' => $dryRun];
        $columns = Cols::all();
        $byTitle = [];
        foreach ($columns as $col) {
            $byTitle[Cols::normalize($col['title'])] = $col;
        }

        $rows = $this->readRows($path);
        $header = null;
        $map = [];
        $dataRows = [];

        foreach ($rows as $line => $cells) {
            if ($header === null) {
                $found = [];
                foreach ($cells as $i => $text) {
                    $n = Cols::normalize($text);
                    if (isset($byTitle[$n])) {
                        $found[$i] = $byTitle[$n];
                    }
                }
                if (collect($found)->contains(fn ($c) => $c['field'] === 'patrimonio')) {
                    $header = $line;
                    $map = $found;
                }

                continue;
            }
            if (collect($cells)->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()) {
                continue;
            }
            $dataRows[$line] = $cells;
        }

        if ($header === null) {
            $result['errors'][] = ['line' => 0, 'patrimonio' => '', 'message' => 'Cabeçalho não encontrado: use o modelo da Oravel (coluna "Nº Patrimônio" obrigatória).'];

            return $result;
        }
        $missing = collect($columns)->filter(fn ($c) => $c['required'])->reject(fn ($c) => collect($map)->contains(fn ($m) => $m['field'] === $c['field']));
        if ($missing->isNotEmpty()) {
            $result['errors'][] = ['line' => $header, 'patrimonio' => '', 'message' => 'Colunas obrigatórias ausentes: '.$missing->pluck('title')->implode(', ')];

            return $result;
        }

        DB::beginTransaction();
        try {
            $seen = [];
            foreach ($dataRows as $line => $cells) {
                $values = [];
                foreach ($map as $i => $col) {
                    $values[$col['target']][$col['field']] = ['col' => $col, 'raw' => $cells[$i] ?? null];
                }
                $patr = trim((string) ($values['asset']['patrimonio']['raw'] ?? ''));

                if ($patr !== '' && isset($seen[mb_strtolower($patr)])) {
                    $result['errors'][] = ['line' => $line, 'patrimonio' => $patr, 'message' => "Nº Patrimônio repetido na planilha (primeira vez na linha {$seen[mb_strtolower($patr)]})."];

                    continue;
                }
                $seen[mb_strtolower($patr)] = $line;

                DB::beginTransaction();
                try {
                    $outcome = $this->importRow($tenant, $values, $updateExisting);
                    DB::commit();
                    $result[$outcome]++;
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $result['errors'][] = ['line' => $line, 'patrimonio' => $patr, 'message' => $e instanceof \DomainException ? $e->getMessage() : 'Erro inesperado: '.$e->getMessage()];
                }
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $result;
    }

    /** @param array<string, array<string, array{col:array, raw:mixed}>> $values */
    private function importRow(Tenant $tenant, array $values, bool $updateExisting): string
    {
        $data = [];
        foreach ($values as $target => $fields) {
            foreach ($fields as $field => $item) {
                $parsed = $this->parse($tenant, $item['col'], $item['raw']);
                if ($parsed !== null) {
                    $data[$target][$field] = $parsed;
                }
            }
        }
        $asset = $data['asset'] ?? [];

        $patr = $asset['patrimonio'] ?? null;
        if (blank($patr)) {
            throw new \DomainException('Nº Patrimônio é obrigatório.');
        }

        $existing = Asset::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('patrimonio', $patr)->first();
        if ($existing && ! $updateExisting) {
            return 'skipped';
        }
        if (! $existing && blank($asset['name'] ?? null)) {
            throw new \DomainException('Nome/Modelo é obrigatório.');
        }

        // refs viram colunas reais
        if (isset($asset['category'])) {
            [$asset['asset_category_id'], $asset['asset_category']] = $asset['category'];
            unset($asset['category']);
        }
        if (isset($asset['internal_unit'])) {
            $asset['internal_unit_id'] = $asset['internal_unit'];
            unset($asset['internal_unit']);
        }
        if (isset($asset['storage_location'])) {
            $unitId = $asset['internal_unit_id'] ?? $existing?->internal_unit_id;
            $asset['storage_location_id'] = $this->findStorageLocation($tenant, $asset['storage_location'], $unitId);
            unset($asset['storage_location']);
        }
        if (isset($asset['criticality'])) {
            $asset['criticidade_peso'] = ['low' => 1, 'medium' => 3, 'high' => 5][$asset['criticality']] ?? 3;
        }
        if (isset($asset['horimetro_atual'])) {
            $asset['last_horimetro'] = $asset['horimetro_atual'];
        }

        if ($existing) {
            $existing->fill($asset)->save();
            $model = $existing;
            $outcome = 'updated';
        } else {
            $model = new Asset($asset + ['status' => Asset::STATUS_DISPONIVEL]);
            $model->tenant_id = $tenant->id;
            $model->save();
            $outcome = 'created';
        }

        foreach (self::SPEC_MODELS as $target => [$class, $relation]) {
            $spec = $data[$target] ?? [];
            if ($spec === []) {
                continue;
            }
            if ($target === 'nr13') {
                $spec['subject_to_nr13'] ??= false;
            }
            $class::withoutGlobalScopes()->updateOrCreate(
                ['asset_id' => $model->id],
                $spec + ['tenant_id' => $tenant->id],
            );
        }

        return $outcome;
    }

    /** @return mixed null quando a célula está vazia */
    private function parse(Tenant $tenant, array $col, mixed $raw): mixed
    {
        if ($raw instanceof \DateTimeInterface) {
            $raw = $raw->format('Y-m-d');
        }
        $text = is_string($raw) ? trim($raw) : $raw;
        if ($text === null || $text === '') {
            return null;
        }
        $title = $col['title'];

        switch ($col['type']) {
            case 'text':
                return is_float($text) && floor($text) === $text ? (string) (int) $text : (string) $text;
            case 'int':
                $n = $this->number($text, $title);

                return (int) round($n);
            case 'decimal':
                return $this->number($text, $title);
            case 'bool':
                $v = Cols::normalize((string) $text);
                if (in_array($v, ['sim', 's', '1', 'true', 'yes'], true)) {
                    return true;
                }
                if (in_array($v, ['nao', 'n', '0', 'false', 'no'], true)) {
                    return false;
                }
                throw new \DomainException("{$title}: use Sim ou Não (veio \"{$text}\").");
            case 'enum':
                $n = Cols::normalize((string) $text);
                foreach ($col['enum'] as $label => $value) {
                    if (Cols::normalize($label) === $n || Cols::normalize((string) $value) === $n) {
                        return $value;
                    }
                }
                throw new \DomainException("{$title}: valor \"{$text}\" não reconhecido. Aceitos: ".implode(', ', array_keys($col['enum'])).'.');
        }

        // ref
        return match ($col['field']) {
            'category' => $this->resolveCategory($tenant, (string) $text),
            'internal_unit' => $this->resolveUnit($tenant, (string) $text),
            'storage_location' => (string) $text,
            default => null,
        };
    }

    private function number(mixed $v, string $title): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = trim((string) $v);
        if (str_contains($s, ',')) {
            $s = str_replace(',', '.', str_replace('.', '', $s));
        }
        if (! is_numeric($s)) {
            throw new \DomainException("{$title}: \"{$v}\" não é um número.");
        }

        return (float) $s;
    }

    /** @return array{0:string,1:string} [id, nome] */
    private function resolveCategory(Tenant $tenant, string $name): array
    {
        $key = $tenant->id.'|'.Cols::normalize($name);
        if (! isset($this->categoryCache[$key])) {
            $cat = AssetCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first()
                ?? AssetCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()
                    ->first(fn ($c) => Cols::normalize($c->name) === Cols::normalize($name))
                ?? AssetCategory::create(['tenant_id' => $tenant->id, 'name' => $name]);
            $this->categoryCache[$key] = $cat->id.'|'.$cat->name;
        }

        return explode('|', $this->categoryCache[$key], 2);
    }

    private function resolveUnit(Tenant $tenant, string $name): string
    {
        $unit = InternalUnit::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()
            ->first(fn ($u) => Cols::normalize($u->name) === Cols::normalize($name));
        if (! $unit) {
            throw new \DomainException("Unidade/Filial Base \"{$name}\" não está cadastrada.");
        }

        return $unit->id;
    }

    private function findStorageLocation(Tenant $tenant, string $code, ?string $unitId): string
    {
        $loc = StorageLocation::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->where('context', StorageLocation::CONTEXT_PATIO_ATIVOS)
            ->when($unitId, fn ($q) => $q->where('internal_unit_id', $unitId))
            ->get()->first(fn ($l) => Cols::normalize((string) $l->code) === Cols::normalize($code));
        if (! $loc) {
            throw new \DomainException("Posição no Pátio \"{$code}\" não existe".($unitId ? ' nesta unidade.' : '.'));
        }

        return $loc->id;
    }

    /** @return array<int, list<mixed>> linha (1-based) => células */
    private function readRows(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $picked = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            $picked ??= $sheet;
            if ($sheet->getName() === Cols::SHEET) {
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
