<?php

namespace App\Domain\Intelligence\Services;

use App\Domain\Intelligence\Models\IntelligenceDiagnostic;
use App\Domain\Intelligence\Models\IntelligenceImport;
use App\Domain\Intelligence\Models\IntelligenceIndicator;
use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Support\Catalogo;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AssetImport\AssetImportColumns as Norm;
use App\Support\ExcelImport\XlsxReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Planilha .xlsx (modelo de Catalogo::campos) → Importação → Snapshot imutável → Indicadores → Diagnóstico.
 * A planilha tem UMA linha de dados = uma fotografia. Nunca sobrescreve: planilha idêntica a um snapshot existente é recusada,
 * e dado novo sempre vira snapshot novo. $simular valida e calcula tudo sem gravar nada.
 */
class ImportadorExcel
{
    public function __construct(private IndicadoresCalculator $indicadores = new IndicadoresCalculator, private DiagnosticoCalculator $diagnostico = new DiagnosticoCalculator) {}

    /**
     * @return array{ok: bool, simulacao: bool, erros: list<string>, import: ?IntelligenceImport, snapshot: ?IntelligenceSnapshot, indicadores: array, diagnostico: array}
     */
    public function importar(string $caminho, Tenant $tenant, ?User $usuario = null, bool $simular = false, ?string $nomeArquivo = null): array
    {
        $r = ['ok' => false, 'simulacao' => $simular, 'erros' => [], 'import' => null, 'snapshot' => null, 'indicadores' => [], 'diagnostico' => []];

        [$dados, $erros] = $this->lerEValidar($caminho);
        if ($erros !== []) {
            $r['erros'] = $erros;

            return $r;
        }

        $hash = hash('sha256', json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $igual = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('hash', $hash)->first();
        if ($igual) {
            $r['erros'][] = 'Esta planilha é idêntica à importada em '.$igual->created_at?->format('d/m/Y H:i').' (referência '.$igual->data_referencia->format('d/m/Y').'). Nada foi gravado.';

            return $r;
        }

        $r['indicadores'] = $this->indicadores->calcular($dados);
        $r['diagnostico'] = $this->diagnostico->calcular($r['indicadores']);
        if ($r['indicadores'] === []) {
            $r['erros'][] = 'Os campos preenchidos não permitem calcular nenhum indicador. Preencha ao menos um bloco completo (ex.: Total de Equipamentos e Equipamentos Locados).';

            return $r;
        }
        if ($simular) {
            $r['ok'] = true;

            return $r;
        }

        $import = IntelligenceImport::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'user_id' => $usuario?->id, 'origem' => 'arquivo', 'arquivo' => $nomeArquivo, 'status' => 'processando',
        ]);
        $r['import'] = $import;

        try {
            DB::transaction(function () use ($tenant, $import, $dados, $hash, &$r) {
                $snapshot = IntelligenceSnapshot::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id, 'import_id' => $import->id, 'data_referencia' => $dados['data_referencia'], 'dados' => $dados, 'hash' => $hash,
                ]);
                foreach ($r['indicadores'] as $chave => $i) {
                    IntelligenceIndicator::withoutGlobalScopes()->create([
                        'tenant_id' => $tenant->id, 'snapshot_id' => $snapshot->id, 'chave' => $chave, 'valor' => $i['valor'], 'unidade' => $i['unidade'],
                        'meta' => ['area' => $i['area'], 'ruim' => $i['ruim'], 'bom' => $i['bom']],
                    ]);
                }
                foreach ($r['diagnostico'] as $area => $d) {
                    IntelligenceDiagnostic::withoutGlobalScopes()->create([
                        'tenant_id' => $tenant->id, 'snapshot_id' => $snapshot->id, 'area' => $area, 'pontuacao' => $d['pontuacao'], 'faixa' => $d['faixa'], 'detalhes' => $d['detalhes'],
                    ]);
                }
                $import->update(['status' => 'concluida', 'concluida_em' => now()]);
                $r['snapshot'] = $snapshot;
            });
            $r['ok'] = true;
        } catch (Throwable $e) {
            report($e);
            $import->update(['status' => 'falhou', 'erro' => mb_substr($e->getMessage(), 0, 500)]);
            $r['erros'][] = 'Falha ao gravar a importação: '.$e->getMessage();
        }

        return $r;
    }

    /** @return array{0: array<string, mixed>, 1: list<string>} dados normalizados (chave => valor) e erros */
    private function lerEValidar(string $caminho): array
    {
        $porTitulo = [];
        foreach (Catalogo::campos() as $campo) {
            $porTitulo[Norm::normalize($campo['title'])] = $campo;
        }

        $mapa = null;
        $linhas = [];
        foreach (XlsxReader::rows($caminho, Catalogo::SHEET) as $n => $celulas) {
            if ($mapa === null) {
                $achados = [];
                foreach ($celulas as $i => $texto) {
                    if (isset($porTitulo[Norm::normalize((string) $texto)])) {
                        $achados[$i] = $porTitulo[Norm::normalize((string) $texto)];
                    }
                }
                if (collect($achados)->contains(fn ($c) => $c['key'] === 'data_referencia')) {
                    $mapa = $achados;
                }

                continue;
            }
            $vazia = collect($celulas)->every(fn ($v) => trim((string) ($v instanceof \DateTimeInterface ? 'x' : $v)) === '');
            $exemplo = str_starts_with(Norm::normalize((string) ($celulas[array_key_first($mapa)] ?? '')), '(exemplo');
            if (! $vazia && ! $exemplo) {
                $linhas[$n] = $celulas;
            }
        }

        if ($mapa === null) {
            return [[], ['Cabeçalho não encontrado: use o modelo da Oravel (coluna "Data de Referência" obrigatória).']];
        }
        if (count($linhas) === 0) {
            return [[], ['A planilha não tem nenhuma linha de dados.']];
        }
        if (count($linhas) > 1) {
            return [[], ['A planilha tem '.count($linhas).' linhas de dados. Cada importação é uma única fotografia da empresa: envie uma linha por arquivo.']];
        }

        $erros = [];
        $dados = [];
        foreach ($mapa as $i => $campo) {
            try {
                $v = $this->converter($campo, array_values($linhas)[0][$i] ?? null);
            } catch (\DomainException $e) {
                $erros[] = $campo['title'].': '.$e->getMessage();

                continue;
            }
            if ($v === null) {
                if ($campo['required']) {
                    $erros[] = $campo['title'].': obrigatório.';
                }

                continue;
            }
            $dados[$campo['key']] = $v;
        }

        if ($erros === []) {
            $erros = $this->coerencia($dados);
        }
        ksort($dados);

        return [$dados, $erros];
    }

    private function converter(array $campo, mixed $bruto): mixed
    {
        if ($bruto instanceof \DateTimeInterface) {
            $bruto = $campo['type'] === 'date' ? $bruto : null;
        }
        if ($campo['type'] === 'date') {
            if ($bruto instanceof \DateTimeInterface) {
                $data = Carbon::instance($bruto);
            } elseif (trim((string) $bruto) === '') {
                return null;
            } else {
                try {
                    $data = Carbon::createFromFormat('d/m/Y', trim((string) $bruto))->startOfDay();
                } catch (Throwable) {
                    throw new \DomainException('data inválida, use dd/mm/aaaa.');
                }
            }
            if ($data->isFuture()) {
                throw new \DomainException('não pode ser uma data futura.');
            }

            return $data->toDateString();
        }

        $texto = trim((string) $bruto);
        if ($texto === '') {
            return null;
        }

        if ($campo['type'] === 'text') {
            return mb_substr($texto, 0, 80);
        }
        if ($campo['type'] === 'enum') {
            foreach ($campo['enum'] as $opcao) {
                if (Norm::normalize($opcao) === Norm::normalize($texto)) {
                    return $opcao;
                }
            }
            throw new \DomainException('use um destes valores: '.implode(', ', $campo['enum']).'.');
        }

        // número: aceita "1.234,56", "1234,56" e "1234.56"
        $n = str_replace(['R$', '%', ' '], '', $texto);
        $n = str_contains($n, ',') ? str_replace(',', '.', str_replace('.', '', $n)) : $n;
        if (! is_numeric($n)) {
            throw new \DomainException('valor numérico inválido.');
        }
        $n = (float) $n;
        if ($n < 0) {
            throw new \DomainException('não pode ser negativo.');
        }
        if ($campo['type'] === 'pct' && $n > 100) {
            throw new \DomainException('percentual deve ficar entre 0 e 100.');
        }
        if ($campo['type'] === 'int') {
            if ($n != floor($n)) {
                throw new \DomainException('deve ser um número inteiro.');
            }

            return (int) $n;
        }

        return $n;
    }

    /** @return list<string> */
    private function coerencia(array $d): array
    {
        $e = [];
        $partes = ($d['frota_locada'] ?? 0) + ($d['frota_indisponivel'] ?? 0);
        if (isset($d['frota_total']) && $partes > $d['frota_total']) {
            $e[] = 'Frota: locados + indisponíveis ('.$partes.') não pode passar do total de equipamentos ('.$d['frota_total'].').';
        }
        if (isset($d['propostas_ganhas'], $d['propostas_emitidas']) && $d['propostas_ganhas'] > $d['propostas_emitidas']) {
            $e[] = 'Comercial: propostas ganhas não pode passar das emitidas.';
        }

        return $e;
    }
}
