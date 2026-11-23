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
use Illuminate\Support\Str;
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
        $r = ['ok' => false, 'simulacao' => $simular, 'erros' => [], 'avisos' => [], 'import' => null, 'snapshot' => null, 'indicadores' => [], 'diagnostico' => [], 'equipamentos' => 0, 'classificacoes' => []];

        [$dados, $erros] = $this->lerEValidar($caminho);
        if ($erros !== []) {
            $r['erros'] = $erros;

            return $r;
        }

        // Aba opcional "Equipamentos": gera campos da empresa, é analisada equipamento a equipamento e entra no snapshot.
        [$equip, $errosEq, $avisos] = $this->lerEquipamentos($caminho, $tenant, $dados['data_referencia']);
        if ($errosEq !== []) {
            $r['erros'] = $errosEq;

            return $r;
        }
        $r['avisos'] = $avisos;
        $analises = [];
        if ($equip !== null) {
            $servico = new AnaliseDeEquipamentos;
            $dados = $this->mesclarAgregados($dados, $servico->agregados($equip, $dados['data_referencia']), $r['avisos']);
            $analises = $servico->analisar($equip, $dados['data_referencia'], $this->anteriores($tenant, $dados['data_referencia']));
            $dados = array_merge($dados, $servico->resumoDaEmpresa($equip, $analises));
            ksort($dados);
            $r['equipamentos'] = count($equip);
            $r['classificacoes'] = array_count_values(array_column($analises, 'classificacao'));
        }

        // Sem a aba, o hash continua sendo o dos dados (importações antigas e novas seguem comparáveis).
        $conteudo = $equip === null ? $dados : ['dados' => $dados, 'equipamentos' => $equip];
        $hash = hash('sha256', json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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
            DB::transaction(function () use ($tenant, $import, $dados, $hash, $equip, $analises, &$r) {
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
                if ($equip !== null) {
                    $this->gravarEquipamentos($tenant->id, $snapshot->id, $equip, $analises);
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

    /**
     * Lê a aba "Equipamentos". Sem a aba: [null, [], []]. Com a aba mas sem a chave de contrato: ignora e avisa.
     *
     * @return array{0: ?list<array<string, mixed>>, 1: list<string>, 2: list<string>} [linhas, erros, avisos]
     */
    private function lerEquipamentos(string $caminho, Tenant $tenant, string $dataReferencia): array
    {
        $rows = XlsxReader::rowsDaAba($caminho, Catalogo::SHEET_EQUIPAMENTOS);
        if ($rows === null) {
            return [null, [], []];
        }
        if (! $tenant->hasFeature('tabela_intelligence_equipamentos')) {
            return [null, [], ['A aba "Equipamentos" foi ignorada: o seu contrato não inclui a análise por equipamento.']];
        }

        $porTitulo = [];
        foreach (Catalogo::equipamentos() as $c) {
            $porTitulo[Norm::normalize($c['title'])] = $c;
        }
        $mapa = null;
        $dadosDaAba = [];
        foreach ($rows as $n => $celulas) {
            if ($mapa === null) {
                $achados = [];
                foreach ($celulas as $i => $texto) {
                    if (isset($porTitulo[Norm::normalize((string) $texto)])) {
                        $achados[$i] = $porTitulo[Norm::normalize((string) $texto)];
                    }
                }
                if (collect($achados)->contains(fn ($c) => $c['key'] === 'patrimonio')) {
                    $mapa = $achados;
                }

                continue;
            }
            $idxPat = array_search('patrimonio', array_column($mapa, 'key'), true);
            $colPat = array_keys($mapa)[$idxPat];
            $vazia = collect($celulas)->every(fn ($v) => trim((string) $v) === '');
            $exemplo = str_starts_with(Norm::normalize((string) ($celulas[$colPat] ?? '')), '(exemplo');
            if (! $vazia && ! $exemplo) {
                $dadosDaAba[$n] = $celulas;
            }
        }

        if ($mapa === null) {
            return [null, ['Aba "Equipamentos": cabeçalho não encontrado. Use o modelo da Oravel (coluna "Nº Patrimônio").'], []];
        }
        $cfg = (array) config('intelligence.equipamentos');
        if (count($dadosDaAba) > (int) $cfg['max_linhas']) {
            return [null, ['Aba "Equipamentos": '.count($dadosDaAba).' linhas e o limite é de '.(int) $cfg['max_linhas'].' por envio.'], []];
        }

        $diasMes = Carbon::parse($dataReferencia)->daysInMonth;
        $anoAtual = (int) Carbon::now()->year;
        $linhas = [];
        $erros = [];
        $vistos = [];
        foreach ($dadosDaAba as $n => $celulas) {
            $l = [];
            $errosLinha = [];
            foreach ($mapa as $i => $campo) {
                try {
                    $v = $this->converter($campo, $celulas[$i] ?? null);
                } catch (\DomainException $e) {
                    $errosLinha[] = $campo['title'].': '.$e->getMessage();

                    continue;
                }
                if ($v === null && $campo['required']) {
                    $errosLinha[] = $campo['title'].': obrigatório.';
                }
                $l[$campo['key']] = $v;
            }
            if (isset($l['patrimonio'])) {
                $l['patrimonio'] = mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $l['patrimonio'])));
                if (isset($vistos[$l['patrimonio']])) {
                    $errosLinha[] = 'Nº Patrimônio repetido (já na linha '.$vistos[$l['patrimonio']].').';
                }
                $vistos[$l['patrimonio']] ??= $n;
            }
            if (isset($l['ano_fabricacao']) && ($l['ano_fabricacao'] < 1950 || $l['ano_fabricacao'] > $anoAtual)) {
                $errosLinha[] = 'Ano de Fabricação: use um ano entre 1950 e '.$anoAtual.'.';
            }
            if (($l['dias_locado'] ?? 0) > $diasMes || ($l['dias_indisponivel'] ?? 0) > $diasMes || (($l['dias_locado'] ?? 0) + ($l['dias_indisponivel'] ?? 0)) > $diasMes) {
                $errosLinha[] = "Dias locado + dias indisponível não podem passar de {$diasMes} (dias do mês de referência).";
            }
            if (isset($l['reincidencias'], $l['os_corretivas']) && $l['reincidencias'] > $l['os_corretivas']) {
                $errosLinha[] = 'Corretivas Reincidentes não pode passar das OS Corretivas.';
            }
            if ($errosLinha !== []) {
                $erros[] = 'Aba "Equipamentos", linha '.$n.(isset($l['patrimonio']) ? ' ('.$l['patrimonio'].')' : '').': '.implode(' ', $errosLinha);
            }
            $linhas[] = $l;
        }
        if (count($erros) > 20) {
            $resto = count($erros) - 20;
            $erros = array_slice($erros, 0, 20);
            $erros[] = "… e mais {$resto} linha(s) com erro na aba \"Equipamentos\".";
        }
        if ($linhas === []) {
            return [null, ['A aba "Equipamentos" não tem nenhuma linha de dados.'], []];
        }

        return [$linhas, $erros, []];
    }

    /**
     * Campos da aba "Dados" que ficaram em branco passam a vir da soma dos equipamentos; o que o cliente digitou prevalece, e uma
     * divergência grande vira aviso (nunca bloqueio).
     *
     * @param  list<string>  $avisos
     */
    private function mesclarAgregados(array $dados, array $agregados, array &$avisos): array
    {
        $titulos = collect(Catalogo::campos())->pluck('title', 'key');
        $tolerancia = (float) config('intelligence.equipamentos.divergencia_aviso_pct', 5);
        $derivados = [];
        foreach ($agregados as $chave => $calculado) {
            if (! array_key_exists($chave, $dados)) {
                $dados[$chave] = $calculado;
                $derivados[] = $chave;

                continue;
            }
            $digitado = (float) $dados[$chave];
            if (abs($digitado - $calculado) / max(1.0, abs($digitado)) * 100 > $tolerancia) {
                $avisos[] = ($titulos[$chave] ?? $chave).': você informou '.$this->fmt($digitado).' e a soma da aba "Equipamentos" dá '.$this->fmt((float) $calculado).'. Foi mantido o que você informou.';
            }
        }
        if ($derivados !== []) {
            sort($derivados);
            $dados['derivados_dos_equipamentos'] = $derivados;
        }
        ksort($dados);

        return $dados;
    }

    private function fmt(float $v): string
    {
        return number_format($v, floor($v) == $v ? 0 : 2, ',', '.');
    }

    /**
     * Equipamentos e análises do snapshot anterior (o mais recente antes desta data que tenha a aba), por patrimônio: dão a tendência
     * e as regras que comparam com o mês anterior.
     *
     * @return array<string, array{linha: array<string, mixed>, analise: array<string, mixed>}>
     */
    private function anteriores(Tenant $tenant, string $dataReferencia): array
    {
        $anterior = IntelligenceSnapshot::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('data_referencia', '<', $dataReferencia)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('intelligence_equipamentos')->whereColumn('intelligence_equipamentos.snapshot_id', 'intelligence_snapshots.id'))
            ->orderByDesc('data_referencia')->orderByDesc('created_at')->first();
        if (! $anterior) {
            return [];
        }
        $linhas = DB::table('intelligence_equipamentos')->where('snapshot_id', $anterior->id)->get()->keyBy('patrimonio');
        $analises = DB::table('intelligence_equipamento_analises')->where('snapshot_id', $anterior->id)->get()->keyBy('patrimonio');
        $out = [];
        foreach ($linhas as $pat => $linha) {
            if (isset($analises[$pat])) {
                $out[$pat] = ['linha' => (array) $linha, 'analise' => (array) $analises[$pat]];
            }
        }

        return $out;
    }

    /** Grava equipamentos e análises em lote (milhares de linhas): ids gerados aqui para ligar a análise ao equipamento. */
    private function gravarEquipamentos(string $tenantId, string $snapshotId, array $linhas, array $analises): void
    {
        $agora = now();
        $eq = [];
        $an = [];
        foreach ($linhas as $l) {
            $id = (string) Str::uuid();
            $eq[] = ['id' => $id, 'tenant_id' => $tenantId, 'snapshot_id' => $snapshotId, 'created_at' => $agora] + array_intersect_key($l, array_flip(array_column(Catalogo::equipamentos(), 'key')));
            $a = $analises[$l['patrimonio']];
            $an[] = ['id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'snapshot_id' => $snapshotId, 'equipamento_id' => $id, 'patrimonio' => $l['patrimonio'], 'created_at' => $agora] + $a;
        }
        foreach (array_chunk($eq, 400) as $lote) {
            DB::table('intelligence_equipamentos')->insert($lote);
        }
        foreach (array_chunk($an, 400) as $lote) {
            DB::table('intelligence_equipamento_analises')->insert($lote);
        }
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
